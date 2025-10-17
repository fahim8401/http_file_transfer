# HTTP File Transfer

A modern, secure PHP-based file transfer application with a beautiful UI, admin panel, and SQLite database. Perfect for sharing files quickly and easily.

## ✨ Features

- **🚀 Easy File Sharing** - Upload files and generate public download links instantly
- **📊 Admin Dashboard** - Full-featured admin panel with file management
- **🔒 Secure** - CSRF protection, session-based authentication, and file type validation
- **💾 No MySQL Required** - Uses lightweight SQLite database
- **📱 Responsive Design** - Works great on desktop and mobile devices
- **🎨 Modern UI** - Beautiful gradient navbar and polished interface
- **📈 Download Tracking** - Monitor file downloads and statistics
- **⏰ Optional Expiration** - Set download limits and expiration dates
- **🔐 Admin Authentication** - Secure login system with password hashing
- **📦 cPanel Compatible** - Easy deployment on shared hosting

## 🖼️ Screenshots

### Upload Page
Modern file upload interface with progress tracking and download link generation.

### Admin Panel
Comprehensive dashboard with file management, statistics, and bulk operations.

### Download Page
Clean download interface with file information and share options.

## 📋 Requirements

- **PHP 8.0 or higher**
- **SQLite3 extension** (usually included with PHP)
- **Web server** (Apache, Nginx, or cPanel)
- **mod_rewrite** (for Apache/cPanel)

## 🚀 Installation

### Standard Installation

1. **Download/Clone the repository**
   ```bash
   git clone https://github.com/fahim8401/http_file_transfer.git
   cd http_file_transfer
   ```

2. **Set permissions**
   ```bash
   chmod 755 .
   chmod 666 filetransfer.db # Will be created automatically
   chmod 755 storage
   ```

3. **Access via browser**
   Navigate to: `http://yoursite.com/`
   
   The database will be created automatically on first access.

### cPanel Installation

1. **Upload Files**
   - Login to cPanel File Manager
   - Navigate to `public_html` (or your domain's root folder)
   - Upload all files from this repository
   - Extract if uploaded as ZIP

2. **Set Permissions**
   - Right-click on the main folder → Change Permissions → 755
   - Create `storage` folder if not exists → Set to 755
   - The `filetransfer.db` will be auto-created with correct permissions

3. **Configure PHP Settings** (Important for large files)
   - Go to cPanel → MultiPHP INI Editor
   - Select your domain
   - Increase these values:
     - `upload_max_filesize` = 2048M (or desired max)
     - `post_max_size` = 2048M
     - `max_execution_time` = 600
     - `max_input_time` = 600
     - `memory_limit` = 512M

4. **Access Your Site**
   - Visit: `http://yourdomain.com/`
   - Upload page will load automatically

## 🔐 Admin Access

**Default Admin Credentials:**
- **Username:** `admin`
- **Password:** `87654321`

**⚠️ IMPORTANT:** Change the default password immediately after first login!

### How to Change Admin Password

Currently, you need to update it in the database. Future versions will include a password change feature in the admin panel.

### Access Admin Panel

Navigate to: `http://yoursite.com/admin.php`

## 📁 Project Structure

```
http_file_transfer/
├── index.php           # Main upload page
├── upload_ajax.php     # AJAX upload handler
├── upload.php          # Alternative form upload
├── dl.php             # Download page
├── download.php        # Download handler
├── admin.php          # Admin panel
├── db.php             # Database connection & initialization
├── helpers.php        # Helper functions (CSRF, validation)
├── cleanup_expired.php # Cleanup script for expired files
├── .htaccess          # Apache/cPanel configuration
├── storage/           # Uploaded files (secured)
└── filetransfer.db    # SQLite database (auto-created)
```

## 🔧 Configuration

### File Upload Limits

Edit `.htaccess` or use cPanel's MultiPHP INI Editor:

```apache
php_value upload_max_filesize 2048M
php_value post_max_size 2048M
php_value max_execution_time 600
```

### Database Location

The SQLite database is created at: `filetransfer.db`

### Storage Directory

Uploaded files are stored in: `storage/`

Both are protected from direct access via `.htaccess`.

## 📊 Admin Panel Features

- **Dashboard Statistics** - Total files, downloads, size, active/expired files
- **File Management** - View, search, filter, and delete files
- **Bulk Operations** - Delete multiple files at once
- **CSV Export** - Export file lists with current filters
- **Download Links** - Copy and share download links
- **Sort & Filter** - Sort by date, size, downloads; filter by status
- **Pagination** - Configurable results per page

## 🔒 Security Features

- ✅ CSRF token protection on all forms
- ✅ Session-based admin authentication
- ✅ Password hashing with bcrypt
- ✅ Dangerous file type blocking (PHP, EXE, etc.)
- ✅ Storage directory protection
- ✅ Database file protection
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (HTML escaping)
- ✅ Security headers in .htaccess

### Blocked File Types

For security, these extensions are blocked:
- Scripts: `.php`, `.asp`, `.jsp`, `.py`, `.pl`, `.rb`
- Executables: `.exe`, `.dll`, `.com`, `.bat`, `.cmd`, `.msi`
- Shell scripts: `.sh`, `.bash`, `.ps1`

PDFs and most other file types are allowed.

## 🛠️ Troubleshooting

### Files Not Uploading

1. Check PHP upload limits in cPanel → MultiPHP INI Editor
2. Verify `storage/` folder exists and is writable (chmod 755)
3. Check PHP error log: `php_errors.log`

### Database Errors

1. Ensure PHP SQLite3 extension is enabled
2. Check file permissions on the directory (should be writable)
3. Database is auto-created; delete `filetransfer.db` to recreate

### Admin Login Not Working

1. Verify default credentials: `admin` / `87654321`
2. Clear browser cookies and try again
3. Check if sessions are enabled in PHP

### .htaccess Not Working (cPanel)

1. Ensure mod_rewrite is enabled
2. Some settings may require php.ini instead of .htaccess
3. Use MultiPHP INI Editor for PHP settings

### Large File Upload Issues

On cPanel:
1. Increase limits in MultiPHP INI Editor
2. Contact hosting support if limits can't be changed
3. Some hosts have hard limits (e.g., 100MB)

## 🔄 Maintenance

### Cleanup Expired Files

Run the cleanup script periodically (can be set up as a cron job):

```bash
php cleanup_expired.php
```

Or via cPanel → Cron Jobs:
```
0 2 * * * php /home/username/public_html/cleanup_expired.php
```

### Backup

Backup these important files:
- `filetransfer.db` - Database with all file records
- `storage/` - All uploaded files

## 🎨 Customization

### Change Branding

Edit the navbar in each PHP file:
```php
<a href="index.php" class="navbar-brand">
  <span>📁</span> Your Brand Name
</a>
```

### Change Colors

The app uses CSS custom properties. Edit the `:root` section in each file's `<style>` tag.

### Add Your Logo

Replace the 📁 emoji with an `<img>` tag in the navbar.

## 📝 License

MIT License - Feel free to use and modify as needed.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📧 Support

For issues and questions:
- Open an issue on GitHub
- Check the troubleshooting section above

## 🔮 Future Enhancements

- [ ] Password change feature in admin panel
- [ ] Multi-user support with roles
- [ ] File preview functionality
- [ ] Drag & drop upload
- [ ] Upload multiple files at once
- [ ] Email notifications
- [ ] Custom themes
- [ ] API for programmatic access

## 🙏 Acknowledgments

Built with:
- Pure PHP (no frameworks)
- SQLite for database
- Bootstrap for some admin panel components
- Modern CSS with gradients and animations

---

**Made with ❤️ for easy file sharing**
