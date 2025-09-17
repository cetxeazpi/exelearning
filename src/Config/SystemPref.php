<?php

namespace App\Config;

use App\Config\Attribute\Setting;

enum SystemPref: string
{
    // Maintenance
    #[Setting(type: 'bool', group: 'maintenance', default: false, label: 'admin.maintenance.title')]
    case MaintenanceEnabled = 'maintenance.enabled';

    #[Setting(type: 'string', group: 'maintenance', default: null, label: 'admin.maintenance.message_label')]
    case MaintenanceMessage = 'maintenance.message';

    #[Setting(type: 'datetime', group: 'maintenance', default: null, label: 'admin.maintenance.scheduled_end')]
    case MaintenanceUntil = 'maintenance.until';

    // Additional HTML
    #[Setting(type: 'html', group: 'additional_html', default: null, label: 'HEAD')]
    case AdditionalHtmlHead = 'additional_html.head';

    #[Setting(type: 'html', group: 'additional_html', default: null, label: 'BODY top')]
    case AdditionalHtmlTop = 'additional_html.top';

    #[Setting(type: 'html', group: 'additional_html', default: null, label: 'Footer')]
    case AdditionalHtmlFooter = 'additional_html.footer';

    // Theme
    #[Setting(type: 'file', group: 'theme', default: null, label: 'Login image path')]
    case ThemeLoginImage = 'theme.login_image_path';

    #[Setting(type: 'file', group: 'theme', default: null, label: 'Login logo path')]
    case ThemeLoginLogo = 'theme.login_logo_path';

    #[Setting(type: 'file', group: 'theme', default: null, label: 'Favicon path')]
    case ThemeFavicon = 'theme.favicon_path';
}
