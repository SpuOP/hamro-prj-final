<?php
session_start();

// Load email renderers
require_once __DIR__ . '/../includes/email_functions.php';

// Helper to get a GET param with default
function get_param(string $key, string $default = ''): string {
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

$type = strtolower(get_param('type', 'approval'));
$full_name = get_param('full_name', 'Alex Johnson');
$email = get_param('email', 'alex@example.com');
$special_id = get_param('special_id', 'SM-24-ABCD12');
$community_name = get_param('community_name', 'Kathmandu Community');
$reason = get_param('reason', 'The submitted document was unclear. Please upload a higher-quality image.');

// Raw mode: output the exact email HTML
if (isset($_GET['raw'])) {
    header('Content-Type: text/html; charset=UTF-8');
    switch ($type) {
        case 'approval':
            echo renderSpecialIDEmailHtml($full_name, $special_id, $community_name);
            break;
        case 'rejection':
            echo renderRejectionEmailHtml($full_name, $reason);
            break;
        case 'confirmation':
        default:
            echo renderApplicationConfirmationEmailHtml($full_name);
            break;
    }
    exit;
}

// Subject line for display
switch ($type) {
    case 'approval':
        $subject = 'CivicPulse - Your Application Approved';
        break;
    case 'rejection':
        $subject = 'CivicPulse - Application Update';
        break;
    case 'confirmation':
    default:
        $subject = 'Application Received - CivicPulse';
        break;
}

// Build preview iframe URL
$query = http_build_query([
    'raw' => 1,
    'type' => $type,
    'full_name' => $full_name,
    'email' => $email,
    'special_id' => $special_id,
    'community_name' => $community_name,
    'reason' => $reason,
]);
$iframeSrc = 'email_preview.php?' . $query;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
            <title>Admin - Email Preview | CivicPulse</title>
    <link rel="stylesheet" href="../assets/css/style.css" />
    <style>
        .container { max-width: 1200px; margin: 24px auto; padding: 0 16px; }
        .panel { background: var(--card-bg, #fff); border-radius: 12px; box-shadow: var(--shadow-md); padding: 16px; }
        .row { display: flex; flex-wrap: wrap; gap: 16px; }
        .col { flex: 1; min-width: 260px; }
        label { font-weight: 600; margin-bottom: 6px; display: block; }
        input, select, textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--border-color, #e5e7eb); border-radius: 8px; background: var(--input-bg, #fff); color: var(--text-dark, #1f2937); }
        textarea { min-height: 88px; resize: vertical; }
        .actions { display: flex; gap: 12px; align-items: center; }
        .btn { background: var(--primary-purple, #7c3aed); border: 0; color: #fff; padding: 10px 14px; border-radius: 10px; cursor: pointer; font-weight: 600; }
        .btn.secondary { background: var(--light-purple, #ede9fe); color: var(--primary-purple, #7c3aed); }
        .preview-wrapper { border: 1px solid var(--border-color, #e5e7eb); border-radius: 12px; overflow: hidden; height: 720px; background: #fff; }
        .meta { margin: 8px 0 16px; font-size: 14px; color: var(--muted-text, #6b7280); }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .brand { font-weight: 800; font-size: 22px; color: var(--primary-purple, #7c3aed); }
        .toggle { display: inline-flex; align-items: center; gap: 10px; }
        .switch { position: relative; width: 56px; height: 30px; background: var(--light-purple, #ede9fe); border-radius: 999px; cursor: pointer; transition: background .25s ease; }
        .switch .thumb { position: absolute; top: 3px; left: 3px; width: 24px; height: 24px; border-radius: 50%; background: #fff; transition: left .25s ease; box-shadow: 0 2px 6px rgba(0,0,0,.2); }
        [data-theme="dark"] .switch { background: #4c1d95; }
        [data-theme="dark"] .switch .thumb { background: #a78bfa; }
        .switch.active .thumb { left: 29px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
                            <div class="brand">CivicPulse — Admin Email Preview</div>
            <div class="toggle">
                <span>Dark mode</span>
                <div id="dmSwitch" class="switch"><div class="thumb"></div></div>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <div class="panel">
                    <form method="get" class="form" action="email_preview.php">
                        <div class="row">
                            <div class="col">
                                <label for="type">Email Type</label>
                                <select id="type" name="type">
                                    <option value="approval" <?php echo $type==='approval'?'selected':''; ?>>Approval (Special ID)</option>
                                    <option value="rejection" <?php echo $type==='rejection'?'selected':''; ?>>Rejection</option>
                                    <option value="confirmation" <?php echo $type==='confirmation'?'selected':''; ?>>Application Confirmation</option>
                                </select>
                            </div>
                            <div class="col">
                                <label for="full_name">Full Name</label>
                                <input id="full_name" type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" />
                            </div>
                            <div class="col">
                                <label for="email">Email</label>
                                <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" />
                            </div>
                        </div>

                        <div class="row" style="margin-top:12px;">
                            <div class="col">
                                <label for="special_id">Special ID</label>
                                <input id="special_id" type="text" name="special_id" value="<?php echo htmlspecialchars($special_id); ?>" />
                            </div>
                            <div class="col">
                                <label for="community_name">Community</label>
                                <input id="community_name" type="text" name="community_name" value="<?php echo htmlspecialchars($community_name); ?>" />
                            </div>
                            <div class="col">
                                <label for="reason">Rejection Reason (optional)</label>
                                <input id="reason" type="text" name="reason" value="<?php echo htmlspecialchars($reason); ?>" />
                            </div>
                        </div>

                        <div class="actions" style="margin-top:16px;">
                            <button class="btn" type="submit">Preview</button>
                            <a class="btn secondary" href="<?php echo htmlspecialchars($iframeSrc); ?>" target="_blank" rel="noopener">Open in new tab</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="panel" style="margin-top:16px;">
            <div class="meta">
                <div><strong>Subject:</strong> <?php echo htmlspecialchars($subject); ?></div>
                <div><strong>To:</strong> <?php echo htmlspecialchars($email); ?></div>
            </div>
            <div class="preview-wrapper">
                <iframe title="Email Preview" src="<?php echo htmlspecialchars($iframeSrc); ?>" style="width:100%;height:100%;border:0;"></iframe>
            </div>
        </div>
    </div>

    <script src="../assets/js/theme.js"></script>
    <script>
        // Wire up local dark-mode switch to theme.js
        (function() {
            var body = document.documentElement;
            var switchEl = document.getElementById('dmSwitch');
            var isDark = (body.getAttribute('data-theme') === 'dark');
            if (isDark) switchEl.classList.add('active');
            switchEl.addEventListener('click', function() {
                var nowDark = body.getAttribute('data-theme') !== 'dark' ? 'dark' : 'light';
                if (nowDark === 'dark') switchEl.classList.add('active'); else switchEl.classList.remove('active');
                // theme.js listens to body attribute changes, we just toggle here
                body.setAttribute('data-theme', nowDark);
                try { localStorage.setItem('civicpulse_theme', nowDark); } catch (e) {}
            });
        })();
    </script>
</body>
</html>


