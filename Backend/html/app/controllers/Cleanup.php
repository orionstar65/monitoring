<?php
require_once BASEPATH . 'app/controllers/Base.php';

/**
 * Cleanup controller
 *
 * Removes stored screenshots (and their thumbnails) for a device / date range
 * selected in the Log Browse page. Deletion is performed in bounded batches so
 * large ranges do not exceed max_execution_time - the caller repeats the request
 * until 'remaining' reaches zero.
 */
class Cleanup extends Base {

    /** Default number of screenshots removed per request */
    const BATCH_DEFAULT = 2000;

    /** Hard ceiling for a single request */
    const BATCH_MAX = 5000;

    public function __construct() {
        parent::__construct();
        $this->requireAdmin();
    }

    /**
     * API endpoint: report what a delete would remove, without touching anything
     */
    public function api_preview_screenshots() {
        $filter = $this->readFilter();
        if (isset($filter['error'])) {
            $this->json(['success' => false, 'error' => $filter['error']]);
            return;
        }

        $summary = $this->db->fetch(
            "SELECT COUNT(*) as total,
                    COALESCE(SUM(file_size), 0) as total_size,
                    MIN(capture_datetime) as oldest,
                    MAX(capture_datetime) as newest
             FROM screenshot_metadata
             {$filter['where']}",
            $filter['params']
        );

        $this->json([
            'success' => true,
            'total' => intval($summary['total']),
            'total_size' => intval($summary['total_size']),
            'total_size_formatted' => $this->formatFileSize(intval($summary['total_size'])),
            'oldest' => $summary['oldest'],
            'newest' => $summary['newest'],
            'device_id' => $filter['device_id'],
            'start_date' => $filter['start_date'],
            'end_date' => $filter['end_date']
        ]);
    }

    /**
     * API endpoint: delete one batch of screenshots and thumbnails
     *
     * Files are removed first, then the matching screenshot_metadata rows. Rows
     * whose files could not be removed are kept so the operation can be retried
     * instead of leaving orphaned files behind.
     */
    public function api_delete_screenshots() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'error' => 'Only POST method allowed']);
            return;
        }

        $filter = $this->readFilter();
        if (isset($filter['error'])) {
            $this->json(['success' => false, 'error' => $filter['error']]);
            return;
        }

        $limit = intval($_POST['limit'] ?? self::BATCH_DEFAULT);
        if ($limit < 1) {
            $limit = self::BATCH_DEFAULT;
        }
        if ($limit > self::BATCH_MAX) {
            $limit = self::BATCH_MAX;
        }

        // Batch size is a validated integer, safe to inline
        $rows = $this->db->fetchAll(
            "SELECT id, file_path, thumbnail_path
             FROM screenshot_metadata
             {$filter['where']}
             ORDER BY capture_datetime ASC, id ASC
             LIMIT {$limit}",
            $filter['params']
        );

        $deleted_files = 0;
        $deleted_thumbnails = 0;
        $freed_bytes = 0;
        $deletable_ids = [];
        $touched_dirs = [];
        $errors = [];

        foreach ($rows as $row) {
            $row_ok = true;

            foreach (['file_path', 'thumbnail_path'] as $column) {
                $resolved = $this->resolveDataPath($row[$column] ?? '');

                // Nothing on disk (already gone, or never written) - the row can go
                if ($resolved === null) {
                    continue;
                }

                $size = @filesize($resolved);
                $dir = dirname($resolved);

                if (@unlink($resolved)) {
                    $freed_bytes += ($size !== false) ? $size : 0;
                    $touched_dirs[$dir] = true;

                    if ($column === 'file_path') {
                        $deleted_files++;
                    } else {
                        $deleted_thumbnails++;
                    }
                } else {
                    $row_ok = false;
                    if (count($errors) < 20) {
                        $errors[] = 'Failed to delete file: ' . $row[$column];
                    }
                }
            }

            if ($row_ok) {
                $deletable_ids[] = intval($row['id']);
            }
        }

        $deleted_rows = 0;
        if (!empty($deletable_ids)) {
            try {
                $placeholders = implode(',', array_fill(0, count($deletable_ids), '?'));
                $stmt = $this->db->query(
                    "DELETE FROM screenshot_metadata WHERE id IN ({$placeholders})",
                    $deletable_ids
                );
                $deleted_rows = $stmt->rowCount();
            } catch (Exception $e) {
                error_log('Cleanup: failed to delete screenshot_metadata rows: ' . $e->getMessage());
                $errors[] = 'Failed to delete database records: ' . $e->getMessage();
            }
        }

        // Drop date directories that this batch emptied
        foreach (array_keys($touched_dirs) as $dir) {
            $this->removeEmptyDateDirectory($dir);
        }

        $remaining = intval($this->db->fetch(
            "SELECT COUNT(*) as total FROM screenshot_metadata {$filter['where']}",
            $filter['params']
        )['total']);

        $this->json([
            'success' => true,
            'deleted_rows' => $deleted_rows,
            'deleted_files' => $deleted_files,
            'deleted_thumbnails' => $deleted_thumbnails,
            'freed_bytes' => $freed_bytes,
            'freed_formatted' => $this->formatFileSize($freed_bytes),
            'remaining' => $remaining,
            'errors' => $errors
        ]);
    }

    /**
     * Read and validate the device / date range filter shared by both endpoints
     */
    private function readFilter() {
        $input = array_merge($_GET, $_POST);

        $device_id = isset($input['device_id']) && $input['device_id'] !== ''
            ? intval($input['device_id'])
            : null;
        $start_date = trim($input['start_date'] ?? '');
        $end_date = trim($input['end_date'] ?? '');

        if (!$this->isValidDate($start_date) || !$this->isValidDate($end_date)) {
            return ['error' => 'A valid start date and end date (YYYY-MM-DD) are required'];
        }

        if (strtotime($start_date) > strtotime($end_date)) {
            return ['error' => 'Start date must not be after end date'];
        }

        $where_conditions = ['capture_datetime >= :start_date', 'capture_datetime <= :end_date'];
        $params = [
            'start_date' => $start_date . ' 00:00:00',
            'end_date' => $end_date . ' 23:59:59'
        ];

        if ($device_id) {
            $where_conditions[] = 'monitor_ip_id = :device_id';
            $params['device_id'] = $device_id;
        }

        return [
            'where' => 'WHERE ' . implode(' AND ', $where_conditions),
            'params' => $params,
            'device_id' => $device_id,
            'start_date' => $start_date,
            'end_date' => $end_date
        ];
    }

    private function isValidDate($date) {
        if (empty($date)) {
            return false;
        }
        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    }

    /**
     * Resolve a stored file_path to an absolute path inside the data directories
     *
     * Stored paths are relative to the backend root (./html/data/...), while the
     * web app runs from BASEPATH (.../html/). Returns null when the file does not
     * exist or resolves outside data/screens and data/thumbnails.
     */
    private function resolveDataPath($path) {
        if (empty($path)) {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        $relative = preg_replace('#^\./#', '', $path);
        $relative = preg_replace('#^html/#', '', $relative);

        $candidates = [
            BASEPATH . $relative,
            BASEPATH . '../' . $relative,
            $path
        ];

        foreach ($candidates as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }

            $real = realpath($candidate);
            if ($real && $this->isInsideDataDirs($real)) {
                return $real;
            }
        }

        return null;
    }

    /**
     * Guard against deleting anything outside the screenshot storage directories
     */
    private function isInsideDataDirs($real_path) {
        static $roots = null;

        if ($roots === null) {
            $roots = [];
            foreach ([SCREENS_DIR, THUMBNAILS_DIR] as $dir) {
                $root = realpath(BASEPATH . $dir);
                if ($root) {
                    $roots[] = rtrim($root, '/') . '/';
                }
            }
        }

        foreach ($roots as $root) {
            if (strpos($real_path, $root) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove a YYYY-MM-DD directory once it is empty (rmdir fails silently otherwise)
     */
    private function removeEmptyDateDirectory($dir) {
        if (!is_dir($dir) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', basename($dir))) {
            return;
        }

        $real = realpath($dir);
        if ($real && $this->isInsideDataDirs($real . '/')) {
            @rmdir($real);
        }
    }

    private function formatFileSize($bytes) {
        if ($bytes <= 0) {
            return '0 Bytes';
        }
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        $i = min(floor(log($bytes) / log($k)), count($sizes) - 1);
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}
?>
