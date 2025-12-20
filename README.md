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

## License

ISC