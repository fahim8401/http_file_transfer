# HTTP File Transfer

A simple PHP-based file transfer application with admin panel and SQLite database.

## Features

- **Public file sharing** - Generate public download links for files
- **No expiration** - Files never expire automatically
- **Admin panel** - Manage uploaded files with secure authentication
- **SQLite database** - Lightweight, no MySQL required
- **Download tracking** - Track how many times files are downloaded
- **Optional download limits** - Set maximum download count per file

## Requirements

- PHP 8.0 or higher
- SQLite3 extension (usually included with PHP)
- Web server (Apache, Nginx, or PHP's built-in server)

## Installation

1. Clone or download this repository
2. Make sure the web server has write permissions for the directory (for creating `filetransfer.db` and `storage/`)
3. Access the application via your web browser
4. The SQLite database will be created automatically on first access

## Admin Access

**Default Admin Credentials:**
- Username: `admin`
- Password: `87654321`

**Important:** Change the default admin password after first login!

To access the admin panel, navigate to: `http://yoursite.com/admin.php`

## Admin Panel Features

- View all uploaded files
- Search and filter files
- Sort by various criteria
- Delete files (admin only)
- Export file list to CSV
- Track download statistics

## File Upload

1. Go to the main page (`index.php`)
2. Click "Choose File" and select a file
3. Click "Start Upload"
4. Share the generated public link

Files are uploaded to the `storage/` directory with randomized names for security.

## Security Notes

- Change the default admin password immediately
- Files never expire but admins can delete them manually
- Dangerous file types (PHP, executable files) are blocked
- CSRF protection on all forms
- Session-based admin authentication
- Password hashing with bcrypt

## Database

The application uses SQLite with the following tables:

- `files` - Stores file metadata
- `users` - Stores admin user credentials

Database file: `filetransfer.db`

## License

MIT License - Feel free to use and modify as needed.
