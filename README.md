# Auto Shop Manager

A PHP-based invoice management system for auto shops with a modern, responsive interface.

## Features

- Create and manage customer invoices
- Customer management with auto-fill functionality
- Service manager assignment
- Print-ready invoice templates
- Modern floating action button interface
- Responsive design for desktop and mobile

## Setup

### Prerequisites

- PHP 7.4 or higher
- MySQL database
- Node.js and npm (for CSS compilation)

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/ftrhpr/autoshop.git
   cd autoshop
   ```

2. Install PHP dependencies (if any) and set up the database:
   - Import `database.sql` into your MySQL database
   - Update `config.php` with your database credentials

3. Install Node.js dependencies and build CSS:
   ```bash
   npm install
   npm run build-css-prod
   ```

4. Start a local server:
   ```bash
   php -S localhost:8000
   # or
   python -m http.server 8000
   ```

5. Open `http://localhost:8000` in your browser

## Development

### CSS Development

This project uses Tailwind CSS for styling. To work with CSS during development:

```bash
# Watch for changes and rebuild CSS automatically
npm run build-css

# Build production CSS (minified)
npm run build-css-prod
```

### File Structure

```
autoshop/
├── src/
│   └── main.css          # Tailwind CSS source
├── dist/
│   └── output.css        # Compiled CSS
├── admin/                # Admin panel files
├── partials/             # Reusable HTML components
├── *.php                 # Main application files
├── tailwind.config.js    # Tailwind configuration
├── postcss.config.js     # PostCSS configuration
└── package.json          # Node.js dependencies
```

## Production Deployment

For production deployment:

1. Run `npm run build-css-prod` to generate minified CSS
2. Ensure the `dist/output.css` file is included in your deployment
3. Remove the Tailwind CDN reference from HTML files
4. Use proper web server (Apache/Nginx) instead of PHP's built-in server

## Contributing

1. Make changes to CSS in `src/main.css`
2. Run `npm run build-css` to compile changes
3. Test thoroughly before committing
4. Follow PHP and CSS best practices

## Realtime Notifications (New)

- Admins and managers will receive near-real-time notifications when a new invoice is created.
- The system polls `api_new_invoices.php` every 8 seconds by default.
- Notifications include a badge in the sidebar, a toast popup, an optional browser notification (requires permission), and a short beep sound generated via the Web Audio API.

How to test:

1. Open the app as a manager or admin (so the notification bell is visible).
2. Create a new invoice in a separate browser window/tab using the New Invoice form.
3. Within ~8 seconds the manager/admin view should show the badge and a toast and play the notification sound. A browser notification will appear if allowed.

Developer shortcut: admins can trigger a test invoice via `admin/test_create_invoice.php` (POST/GET) which inserts a dummy invoice — this helps verifying notifications quickly.

## Live Invoice Updates in Manager Panel

- The manager panel (`manager.php`) now shows live updates for new invoices.
- New invoices appear at the top of the table with a blinking yellow highlight.
- The blinking stops when you click "View" or click anywhere on the invoice row.
- Invoices remain highlighted (non-blinking) for 30 seconds after being viewed.
- Updates poll every 10 seconds and work across browser tabs using sessionStorage.

To customize polling interval or behavior, edit `partials/sidebar.php` and adjust `pollingInterval` inside the notification script.

Sound file:
- By default the app serves a small built-in WAV from `assets/sounds/notify.php`.
- For better quality, place `notify.mp3` and/or `notify.ogg` in `assets/sounds/` — the app will prefer those files automatically.
- To use a custom WAV file instead of the server endpoint, add `notify.wav` to the same folder and update `assets/sounds/notify.php` or remove it.


## License

ISC