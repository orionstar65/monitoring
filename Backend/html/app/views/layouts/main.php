<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']??'',ENT_QUOTES,'UTF-8') ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/3.1.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/colreorder/3.1.2/css/colReorder.bootstrap5.min.css" rel="stylesheet">
    <script>
    // Shared stable ordering for web user/device selectors. Keep the label
    // format independent of sorting, and do not mutate the API response.
    window.sortMonitorOptions = function(items) {
      const collator = new Intl.Collator(undefined, {numeric:true, sensitivity:'base'});
      const value = (item, keys) => {
        for (const key of keys) {
          const result = String(item?.[key] ?? '').trim();
          if (result) return result;
        }
        return '';
      };
      return [...items].sort((a,b) => {
        const ano=value(a,['user_no','owner_user_no']);
        const bno=value(b,['user_no','owner_user_no']);
        if (!ano !== !bno) return ano ? -1 : 1;
        for (const keys of [
          ['user_no','owner_user_no'],
          ['username','owner_name','full_name'],
          ['type'],
          ['platform','os'],
          ['last_seen_ip','ip_address'],
          ['id']
        ]) {
          const difference=collator.compare(value(a,keys),value(b,keys));
          if (difference) return difference;
        }
        return 0;
      });
    };
    </script>
    <style>
        :root {
            --primary-color: #1976d2;
            --primary-dark: #1565c0;
            --primary-light: #42a5f5;
            --secondary-color: #424242;
            --accent-color: #ff4081;
            --background-color: #fafafa;
            --surface-color: #ffffff;
            --text-primary: #212121;
            --text-secondary: #757575;
            --divider-color: #e0e0e0;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --error-color: #f44336;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 72px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--background-color);
            color: var(--text-primary);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        /* Modern Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .sidebar-header h4 {
            color: white;
            font-weight: 600;
            font-size: 1.25rem;
            margin: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .sidebar-header h4 {
            opacity: 0;
        }

        .sidebar-nav {
            padding: 16px 0;
        }

        .nav-item {
            margin: 4px 12px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.2s ease;
            font-weight: 500;
            position: relative;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(4px);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 24px;
            background: white;
            border-radius: 0 2px 2px 0;
        }

        .nav-link i {
            width: 24px;
            text-align: center;
            margin-right: 12px;
            font-size: 1.1rem;
        }

        .sidebar.collapsed .nav-link span {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 12px;
        }

        .sidebar.collapsed .nav-link i {
            margin-right: 0;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--background-color);
        }

        /* Sticky footer without a fixed overlay. Only the outer page shell
           becomes a flex column; nested monitoring panels also use
           .main-content and must keep their own layout behavior. */
        #mainContent {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            min-height: 100dvh;
        }
        #mainContent > .content-area {
            flex: 1 0 auto;
            width: 100%;
        }
        #mainContent > .footer {
            flex-shrink: 0;
            margin-top: auto;
        }

        .main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        .main-content.full-width {
            margin-left: 0;
        }

        /* Top Bar */
        .top-bar {
            background: var(--surface-color);
            border-bottom: 1px solid var(--divider-color);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.2rem;
            padding: 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .sidebar-toggle:hover {
            background: var(--divider-color);
            color: var(--text-primary);
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Content Area */
        .content-area {
            padding: 24px;
            margin: 0 auto;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            background: var(--surface-color);
            transition: box-shadow 0.2s ease;
        }

        .card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--divider-color);
            padding: 20px 24px;
            border-radius: 16px 16px 0 0;
        }

        .card-body {
            padding: 24px;
        }

        /* Buttons */
        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 10px 20px;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #45a049;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        /* Alerts */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: rgba(244, 67, 54, 0.1);
            color: var(--error-color);
            border-left: 4px solid var(--error-color);
        }

        /* Footer */
        .footer {
            background: var(--surface-color);
            border-top: 1px solid var(--divider-color);
            padding: 24px;
            margin-top: 0;
            text-align: center;
            color: var(--text-secondary);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .footer-links {
            display: flex;
            gap: 24px;
        }

        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: var(--sidebar-width);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .main-content.expanded {
                margin-left: 0;
            }

            .sidebar-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }

            .content-area {
                padding: 16px;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
            }
        }

        /* Legacy styles for existing components */
        .screenshot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }

        .screenshot-card {
            border: 1px solid var(--divider-color);
            border-radius: 12px;
            overflow: hidden;
            background: var(--surface-color);
        }

        .screenshot-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .logs-sidebar {
            height: calc(100vh - 200px);
            overflow-y: auto;
        }

        .logs-content {
            height: calc(100vh - 200px);
            overflow-y: auto;
        }

        .log-file {
            cursor: pointer;
            padding: 12px 16px;
            border-bottom: 1px solid var(--divider-color);
            transition: background-color 0.2s ease;
        }

        .log-file:hover {
            background: var(--divider-color);
        }

        .log-file.active {
            background: rgba(25, 118, 210, 0.1);
            border-left: 4px solid var(--primary-color);
        }

        /* Table improvements */
        .table {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Consistent, accessible table headings across Bootstrap, DataTables,
           report tables and log tables. Override Bootstrap's table cell CSS
           variables on the actual <th>, not just the table, so its generated
           background layer cannot hide the header background. */
        .table thead th,
        table.dataTable thead th,
        .reports-table thead th,
        .log-table thead th {
            --bs-table-bg: #e8f0fa;
            --bs-table-color: #243449;
            --bs-table-accent-bg: transparent;
            background-color: #e8f0fa;
            background-image: none;
            color: #243449;
            border-bottom: 1px solid #cedae9;
            box-shadow: inset 0 -1px 0 #cedae9;
            font-weight: 600;
            padding: 14px 16px;
        }

        /* Keep sortable DataTables headings readable when hovered or focused. */
        table.dataTable thead th.dt-orderable-asc:hover,
        table.dataTable thead th.dt-orderable-desc:hover,
        table.dataTable thead th:focus-visible {
            --bs-table-bg: #dce8f7;
            background-color: #dce8f7;
            color: #18283d;
        }

        .table tbody td {
            padding: 16px;
            border-color: var(--divider-color);
        }

        .table tbody tr:hover {
            background: rgba(25, 118, 210, 0.05);
        }
    
        /* Consistent compact controls across management and monitoring pages. */
        .btn{font-size:.84rem;padding:.34rem .65rem;line-height:1.3;border-radius:6px}
        .btn-sm{font-size:.77rem;padding:.22rem .48rem;line-height:1.25;border-radius:5px}
        .btn-lg{font-size:.98rem;padding:.58rem .98rem}
        .btn i{vertical-align:baseline}
        .card-header{padding:.7rem .85rem}
        .table>:not(caption)>*>*{padding:.58rem .65rem}
        .form-control-sm,.form-select-sm{min-height:30px;font-size:.8rem}
    </style>
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- Modern Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4><?php echo APP_NAME; ?></h4>
        </div>
        <div class="sidebar-nav">
            <?php $privileged=($_SESSION['role']??'')===ROLE_SUPER_ADMIN;
                  $delegated=($_SESSION['role']??'')===ROLE_SCOPED_ADMIN; ?>
            <?php if($privileged): ?>
            <div class="nav-item">
                <a class="nav-link <?= $active_page==='dashboard'?'active':'' ?>" href="<?= BASE_URL ?>dashboard">
                    <i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            </div>
            <?php endif; ?>
            <?php if($privileged||$delegated): ?>
            <div class="nav-item">
                <a class="nav-link <?= $active_page==='users'?'active':'' ?>" href="<?= BASE_URL ?>users">
                    <i class="fas fa-users"></i><span>User Management</span></a>
            </div>
            <?php endif; ?>
            <div class="nav-item">
                <a class="nav-link <?= $active_page==='monitors'?'active':'' ?>" href="<?= BASE_URL ?>monitors">
                    <i class="fas fa-desktop"></i><span>Monitors</span></a>
            </div>
            <?php if($privileged||$delegated): ?>
            <div class="nav-item">
                <a class="nav-link <?= $active_page==='view'?'active':'' ?>" href="<?= BASE_URL ?>view">
                    <i class="fas fa-eye"></i><span>Monitoring</span></a>
            </div>
            <?php endif; ?>
            <?php if($privileged): ?>
            <div class="nav-item">
                <a class="nav-link <?= $active_page==='api-logs'?'active':'' ?>" href="<?= BASE_URL ?>apilogs">
                    <i class="fas fa-list-alt"></i><span>API Logs</span></a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?= $active_page==='reports'?'active':'' ?>" href="<?= BASE_URL ?>reports">
                    <i class="fas fa-chart-line"></i><span>Reports</span></a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?= $active_page==='export'?'active':'' ?>" href="<?= BASE_URL ?>export">
                    <i class="fas fa-download"></i><span>Export</span></a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?= $active_page==='db-management'?'active':'' ?>" href="<?= BASE_URL ?>databasemanagement">
                    <i class="fas fa-database"></i><span>Database Management</span></a>
            </div>
            <?php endif; ?>
            <?php if(($_SESSION['role']??'')==='user'): ?>
            <div class="nav-item">
                <a class="nav-link <?= $active_page==='view'?'active':'' ?>" href="<?= BASE_URL ?>view">
                    <i class="fas fa-chart-bar"></i><span>Monitoring totals</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?= $active_page==='logbrowse'?'active':'' ?>" href="<?= BASE_URL ?>logbrowse">
                    <i class="fas fa-list-ol"></i><span>Log totals</span>
                </a>
            </div>
            <?php endif; ?>
            <div class="nav-item">
                <a class="nav-link <?php echo $active_page === 'password' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>auth/change_password">
                    <i class="fas fa-key"></i><span>Change Password</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?php echo $active_page === 'logout' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>auth/logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title"><?php echo $page_title ?? 'Dashboard'; ?></h1>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <?php echo $content ?? ''; ?>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <div class="footer-content">
                <div>
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="footer-links">
                    <a href="<?php echo BASE_URL; ?>dashboard">Dashboard</a>
                    <a href="<?php echo BASE_URL; ?>monitors">Monitors</a>
                    <a href="<?php echo BASE_URL; ?>view">Monitoring</a>
                    <a href="<?php echo BASE_URL; ?>auth/logout">Logout</a>
                </div>
            </div>
        </footer>
    </main>
    <?php else: ?>
        <?php echo $content ?? ''; ?>
    <?php endif; ?>

    <script src="https://cdn.datatables.net/3.1.5/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/3.1.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/colreorder/3.1.2/js/dataTables.colReorder.min.js"></script>
    <script src="https://cdn.datatables.net/colreorder/3.1.2/js/colReorder.bootstrap5.min.js"></script>
    <script>
    (() => {
      const token=document.querySelector('meta[name="csrf-token"]')?.content||'';
      document.addEventListener('submit',(event)=>{
        const form=event.target;
        if(!(form instanceof HTMLFormElement)||form.method.toUpperCase()!=='POST')return;
        if(!form.querySelector('input[name="csrf_token"]')){
          const field=document.createElement('input');field.type='hidden';field.name='csrf_token';field.value=token;form.appendChild(field);
        }
      },true);
      const oldFetch=window.fetch.bind(window);
      window.fetch=(input,options={})=>{
        const method=(options.method||(input instanceof Request?input.method:'GET')).toUpperCase();
        const url=new URL(typeof input==='string'?input:input.url,location.href);
        if(url.origin===location.origin && !['GET','HEAD','OPTIONS'].includes(method)){
          options={...options,headers:new Headers(options.headers||(input instanceof Request?input.headers:undefined))};
          options.headers.set('X-CSRF-Token',token);
        }
        return oldFetch(input,options);
      };
      if(window.jQuery)jQuery(document).ajaxSend((_event,xhr,settings)=>{
        if(!/^(GET|HEAD|OPTIONS)$/i.test(settings.type||'GET'))xhr.setRequestHeader('X-CSRF-Token',token);
      });
    })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Modern sidebar functionality
    $(document).ready(function() {
        const sidebar = $('#sidebar');
        const mainContent = $('.main-content');
        const sidebarToggle = $('#sidebarToggle');
        const toggleIcon = sidebarToggle.find('i');
        
        // Check if sidebar state is saved in localStorage
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        // Initialize sidebar state
        if (sidebarCollapsed && window.innerWidth >= 768) {
            sidebar.addClass('collapsed');
            mainContent.addClass('expanded');
            toggleIcon.removeClass('fa-bars').addClass('fa-chevron-left');
        }
        
        // Toggle button click handler
        sidebarToggle.on('click', function() {
            if (window.innerWidth < 768) {
                // Mobile behavior
                if (sidebar.hasClass('show')) {
                    sidebar.removeClass('show');
                    $('.sidebar-backdrop').remove();
                } else {
                    sidebar.addClass('show');
                    $('body').append('<div class="sidebar-backdrop"></div>');
                    
                    // Close sidebar when clicking backdrop
                    $('.sidebar-backdrop').on('click', function() {
                        sidebar.removeClass('show');
                        $(this).remove();
                    });
                }
            } else {
                // Desktop behavior
                if (sidebar.hasClass('collapsed')) {
                    sidebar.removeClass('collapsed');
                    mainContent.removeClass('expanded');
                    toggleIcon.removeClass('fa-chevron-left').addClass('fa-bars');
                    localStorage.setItem('sidebarCollapsed', 'false');
                } else {
                    sidebar.addClass('collapsed');
                    mainContent.addClass('expanded');
                    toggleIcon.removeClass('fa-bars').addClass('fa-chevron-left');
                    localStorage.setItem('sidebarCollapsed', 'true');
                }
            }
        });
        
        // Handle window resize
        $(window).on('resize', function() {
            if (window.innerWidth >= 768) {
                // Desktop mode
                sidebar.removeClass('show');
                $('.sidebar-backdrop').remove();
                if (sidebarCollapsed) {
                    sidebar.addClass('collapsed');
                    mainContent.addClass('expanded');
                    toggleIcon.removeClass('fa-bars').addClass('fa-chevron-left');
                }
            } else {
                // Mobile mode
                sidebar.removeClass('collapsed');
                mainContent.removeClass('expanded');
                toggleIcon.removeClass('fa-chevron-left').addClass('fa-bars');
            }
        });
        
        // Close mobile sidebar when clicking a link
        sidebar.find('.nav-link').on('click', function() {
            if (window.innerWidth < 768) {
                sidebar.removeClass('show');
                $('.sidebar-backdrop').remove();
            }
        });
        
        // Keyboard shortcut: Ctrl/Cmd + B to toggle sidebar
        $(document).on('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                sidebarToggle.click();
            }
        });

        // Add smooth scrolling to all links
        $('a[href^="#"]').on('click', function(e) {
            e.preventDefault();
            const target = $(this.getAttribute('href'));
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 100
                }, 500);
            }
        });

        // Add loading states to buttons
        $('.btn').on('click', function() {
            const btn = $(this);
            if (btn.attr('type') === 'submit' && !btn.hasClass('btn-loading')) {
                btn.addClass('btn-loading');
                const originalText = btn.html();
                btn.html('<i class="fas fa-spinner fa-spin me-2"></i>Loading...');
                
                // Reset after 3 seconds (fallback)
                setTimeout(() => {
                    btn.removeClass('btn-loading');
                    btn.html(originalText);
                }, 3000);
            }
        });
    });
    </script>
    
    <script>
    // Persistent sortable, reorderable, filterable tables.
    // Independent state keys prevent one signed-in user inheriting another's layout.
    document.addEventListener('DOMContentLoaded',()=>{
      if(!window.DataTable)return;
      const uid=<?= json_encode((string)($_SESSION['user_id']??'guest')) ?>;
      document.querySelectorAll('table.advanced-table').forEach((table,index)=>{
        if(table.dataset.initialized)return;
        table.dataset.initialized='1';
        if(!table.id)table.id='advanced-table-'+index;
        const key='acc-monitor-v14:tables:'+uid+':'+location.pathname+':'+table.id;
        const instance=new DataTable(table,{
          pageLength:20,
          lengthMenu:[10,20,50,100],
          stateSave:true,
          stateDuration:0,
          colReorder:true,
          order:[],
          columnDefs:[{targets:'_all',defaultContent:''}],
          stateSaveCallback:(_settings,data)=>{try{localStorage.setItem(key,JSON.stringify(data))}catch(_){}},
          stateLoadCallback:()=>{try{return JSON.parse(localStorage.getItem(key)||'null')}catch(_){return null}},
          language:{searchPlaceholder:'Search records...',search:'Filter:'}
        });
        // Column-specific filters persist via the DataTables state callback.
        const container=document.createElement('details');
        container.className='mb-3 small';
        const caption=document.createElement('summary');
        caption.className='text-primary fw-medium';caption.textContent='Column filters';
        container.append(caption);
        const fields=document.createElement('div');
        fields.className='row row-cols-2 row-cols-md-3 row-cols-xl-4 g-2 mt-1';
        table.querySelectorAll('thead tr:first-child th').forEach((header,originalIndex)=>{
          if(header.hasAttribute('data-dt-order'))return;
          const div=document.createElement('div');
          const label=document.createElement('label');
          label.className='form-label mb-1';label.textContent=header.textContent.trim();
          const input=document.createElement('input');
          input.type='search';input.className='form-control form-control-sm';
          input.setAttribute('aria-label','Filter by '+label.textContent);
          // Use named columns so filtering still maps correctly after drag-reorder.
          const col=instance.column(header);
          input.value=col.search();
          input.addEventListener('input',()=>col.search(input.value).draw());
          div.append(label,input);fields.append(div);
        });
        container.append(fields);
        table.closest('.table-responsive')?.parentElement?.prepend(container);
      });
    });
    </script>
    <?php if (isset($scripts)): ?>
        <?php echo $scripts; ?>
    <?php endif; ?>
</body>
</html> 