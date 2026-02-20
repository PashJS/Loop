# Large File Upload Support (256GB)

FloxWatch now supports video uploads up to **256GB** using chunked upload technology.

## Configuration Requirements

### 1. PHP Configuration

Update your `php.ini` file (or create a `.user.ini` in the root directory) with these settings:

```ini
upload_max_filesize = 256G
post_max_size = 256G
memory_limit = 512M
max_execution_time = 0
max_input_time = 0
file_uploads = On
```

**For MAMP on Windows:**
- Edit `C:\MAMP\bin\php\php[version]\php.ini`
- Restart MAMP after making changes

### 2. Apache/.htaccess Configuration

The `.htaccess` file in the root directory includes PHP settings. If your server doesn't allow `.htaccess` overrides, you'll need to configure these in your Apache `httpd.conf`:

```apache
php_value upload_max_filesize 256G
php_value post_max_size 256G
php_value memory_limit 512M
php_value max_execution_time 0
```

### 3. Disk Space

Ensure you have at least **256GB** of free disk space in your uploads directory:
- `uploads/videos/` - for video files
- `uploads/temp/` - for temporary chunk files during upload

## How It Works

1. **Chunked Uploads**: Large files are split into chunks (1-20MB depending on file size)
2. **Adaptive Chunking**: 
   - Files < 100MB: 1MB chunks
   - Files 100MB-1GB: 5MB chunks
   - Files 1GB-10GB: 10MB chunks
   - Files > 10GB: 20MB chunks
3. **Storage Strategy**:
   - Files < 4GB: Stored in database (`file_storage` table)
   - Files ≥ 4GB: Stored on filesystem (`uploads/videos/`)
4. **Progress Tracking**: Real-time upload progress with speed and ETA
5. **Retry Logic**: Automatic retry (3 attempts) for failed chunks
6. **Resumable Uploads**: Upload sessions are tracked, allowing resumption if interrupted

## Supported Video Formats

- MP4, WebM, OGG, MOV
- AVI, MKV, FLV, WMV, M4V

## Testing Upload Settings

Visit `backend/check_upload_settings.php` to verify your PHP and server configuration.

## Troubleshooting

### "File size exceeds PHP limit"
- Check `php.ini` settings
- Verify `.htaccess` is being read
- Restart your web server

### "Insufficient disk space"
- Free up space in `uploads/` directory
- Check disk quota if on shared hosting

### "Upload timeout"
- Increase `max_execution_time` to `0` (unlimited)
- Check network connection stability
- For very large files (>50GB), consider using a more stable connection

### "Chunk upload failed"
- Check server error logs
- Verify `uploads/temp/` directory is writable
- Ensure PHP `upload_tmp_dir` has sufficient space

## Performance Notes

- **Upload Speed**: Depends on your internet connection and server bandwidth
- **Processing Time**: Large files may take hours to upload
- **Browser**: Keep the upload page open during the entire upload process
- **Network**: Use a stable, high-speed connection for best results

## Security Considerations

- File type validation is enforced on both client and server
- File size limits prevent abuse
- Upload sessions are tied to authenticated users
- Temporary chunk files are cleaned up after upload completion

