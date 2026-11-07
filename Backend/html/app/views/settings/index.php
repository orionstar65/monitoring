<?php
$title='Operational Settings - '.APP_NAME;
$active_page='settings';
$page_title='Operational Settings';
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$groups=[];
foreach($catalog as $key=>$entry)$groups[$entry[0]][$key]=$entry;
ob_start();
?>
<div class="container-fluid px-2 px-lg-3" style="max-width:1420px">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
      <h4 class="mb-1">Operational Settings</h4>
      <p class="text-muted small mb-0">Super Admin · Centralized typed key/value settings for clients and backend services</p>
    </div>
    <a href="<?= BASE_URL ?>settings" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sync-alt me-1"></i>Refresh</a>
  </div>
  <?php if($migrationNeeded): ?>
    <div class="alert alert-warning py-2">
      The <code>app_settings</code> table is missing or unavailable. Apply
      <code>Backend/migrations/2026_11_07_application_settings.sql</code>
      before saving changes. Read-only defaults are shown.
    </div>
  <?php endif; ?>
  <?php if(!empty($notice)): ?>
    <div class="alert alert-success py-2"><?= $e($notice) ?></div>
  <?php endif; ?>
  <div class="alert alert-light border py-2 small">
    Settings are validated against supported keys. Global and OS-specific client
    <strong>intervals</strong> take effect when each client next refreshes
    <code>/webapi_config.php</code>. API feature flags and network defaults are returned
    by that endpoint but are not yet uniformly enforced by all client versions.
    Do not rely on a feature flag alone to disable collection.
    The API logging flag applies to new requests, and the retention default changes
    the initial cleanup form value. Credentials, tokens and release paths are excluded.
    <strong>Intervals are in seconds; retention is in months.</strong>
  </div>
  <?php foreach($groups as $group=>$entries): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-body-tertiary py-2">
        <strong><?= $e($group) ?></strong>
        <?php if(str_contains($group,'overrides')): ?>
          <small class="text-muted ms-2">Blank = use global interval</small>
        <?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
          <thead class="table-light"><tr>
            <th style="min-width:270px">Setting key</th><th style="min-width:175px">Description</th>
            <th style="min-width:230px">Value</th><th>Default / range</th><th>Last updated</th><th class="text-end">Actions</th>
          </tr></thead>
          <tbody>
          <?php foreach($entries as $key=>$def):
              [$section,$label,$type,$default,$min,$max]=$def;
              $row=$stored[$key]??null;
              $value=$row ? $row['setting_value'] : ($type==='optional_int'?'':($type==='bool'?($default?'1':'0'):(string)$default));
              $formId='setting-'.substr(hash('sha256',$key),0,12);
              $unit=$key==='retention.default_months'?' months'
                  :(in_array($key,['server.max_retry_attempts','server.chunk_size'],true)?'':' seconds');
              $defaultLabel=$type==='bool' ? ($default?'Enabled':'Disabled')
                  :($default===null?'Inherit global':$default.$unit);
          ?>
            <tr>
              <td><code><?= $e($key) ?></code></td>
              <td><?= $e($label) ?></td>
              <td>
                <form id="<?= $formId ?>" action="<?= BASE_URL ?>settings/save" method="post" class="m-0">
                  <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']??'') ?>">
                  <input type="hidden" name="key" value="<?= $e($key) ?>">
                  <?php if($type==='bool'): ?>
                    <select name="value" class="form-select form-select-sm" aria-label="<?= $e($label) ?>">
                      <option value="1" <?= $value==='1'?'selected':'' ?>>Enabled</option>
                      <option value="0" <?= $value==='0'?'selected':'' ?>>Disabled</option>
                    </select>
                  <?php else: ?>
                    <input type="number" name="value" class="form-control form-control-sm"
                      value="<?= $e($value) ?>" min="<?= (int)$min ?>" max="<?= (int)$max ?>" step="1"
                      <?= $type==='optional_int'?'placeholder="Inherit global"':'required' ?>
                      aria-label="<?= $e($label) ?>">
                  <?php endif; ?>
                </form>
              </td>
              <td class="small text-muted">
                <?= $e($defaultLabel) ?>
                <?php if($min!==null): ?><span class="text-nowrap">(<?= (int)$min ?>–<?= (int)$max ?>)</span><?php endif; ?>
              </td>
              <td class="text-muted small">
                <?= $row ? $e($row['updated_at']):'Default' ?>
              </td>
              <td class="text-end text-nowrap">
                <button type="submit" form="<?= $formId ?>" class="btn btn-sm btn-primary" <?= $migrationNeeded?'disabled':'' ?>>Save</button>
                <?php if($row): ?>
                  <form action="<?= BASE_URL ?>settings/reset" method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']??'') ?>">
                    <input type="hidden" name="key" value="<?= $e($key) ?>">
                    <button class="btn btn-sm btn-outline-secondary" type="submit" title="Restore default">Reset</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
  <p class="text-muted small">
    Platform-specific keys override global intervals only for their operating system.
    Existing clients must support the corresponding API fields to apply these changes.
  </p>
</div>
<?php $content=ob_get_clean();include BASEPATH.'app/views/layouts/main.php'; ?>
