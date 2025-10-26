# Forgot Password Feature Setup Guide

## Overview
This guide will help you set up the password reset functionality using PHPMailer with Gmail.

## Files Involved
- `pages/send_reset_email.php` - Handles sending password reset emails
- `pages/forgot_password.php` - Handles the password reset form
- `pages/login.php` - Contains the "Forgot Password" button
- `pages/check_and_add_columns.php` - Database setup script

## Step 1: Database Setup

### Run the Database Setup Script
1. Open your browser and navigate to:
   ```
   http://localhost/pages/check_and_add_columns.php
   ```
2. This script will automatically add the required columns to your `users` table:
   - `password_reset_token` (VARCHAR 64)
   - `password_reset_expires` (DATETIME)
   - `updated_at` (TIMESTAMP)
   - `status` (VARCHAR 20) with default 'active'

## Step 2: Gmail Configuration

### Get Gmail App Password
1. Go to your Google Account: https://myaccount.google.com/
2. Navigate to **Security** section
3. Enable **2-Step Verification** (if not already enabled)
4. Click on **App passwords** (you may need to search for it)
5. Select **Mail** as the app and **Other** as the device
6. Enter "Inventory System" as the name
7. Click **Generate**
8. Copy the 16-character password (shown like: `abcd efgh ijkl mnop`)

### Update Gmail Credentials
1. Open `pages/send_reset_email.php`
2. Find lines 117-118:
   ```php
   $gmailUser = 'your-email@gmail.com'; // Replace with your Gmail
   $gmailAppPass = 'your-app-password'; // Replace with your App Password
   ```
3. Replace with your actual credentials:
   ```php
   $gmailUser = 'your-actual-email@gmail.com';
   $gmailAppPass = 'your-16-char-app-password';
   ```

## Step 3: Testing the Feature

### Test the Complete Flow
1. Open your login page: `http://localhost/pages/login.php`
2. Click **"Forgot password?"** button
3. Enter a registered email address
4. Click **"Send Reset Link"**
5. Check your inbox (and spam folder) for the reset email
6. Click the reset link in the email
7. Enter a new password and confirm it
8. Click **"Set new password"**
9. You should see a success message with a link to login

### Debug Mode
If you encounter issues, you can enable debug mode:
1. Add `?debug=1` to the URL when sending reset email
2. Or set environment variable: `DEBUG=1`
3. This will show detailed error messages in the JSON response

## Troubleshooting

### Email Not Sending
1. **Check Gmail App Password**: Make sure you copied the App Password correctly (no spaces)
2. **Check Gmail Settings**: Ensure "Less secure app access" is enabled OR use App Password
3. **Check Log File**: Look at `pages/send_reset_email.log` for errors
4. **Check Firewall**: Port 587 might be blocked by your firewall or ISP
5. **Check PHPMailer Path**: Verify `PHPMailer/src` folder exists at the correct location

### Database Errors
1. Run the database setup script again: `check_and_add_columns.php`
2. Check if `users` table exists in `inventory_system_db` database
3. Verify database credentials in `send_reset_email.php`

### Reset Link Not Working
1. Check the URL in the email - it should point to `forgot_password.php`
2. Verify the token is being generated (should be 64 hex characters)
3. Check if the token hasn't expired (30 minutes default)
4. Verify the email address matches exactly with database

## Security Features

### Implemented Security Measures
- ✅ Tokens are hashed using SHA-256 before storing in database
- ✅ Tokens expire after 30 minutes
- ✅ Generic success messages (don't reveal if email exists)
- ✅ Token validation on both reset request and password update
- ✅ Prevents timing attacks with generic responses
- ✅ HTML email with plain-text alternative
- ✅ Secure password hashing using `password_hash()`

### Additional Recommendations
- Set up rate limiting for password reset requests
- Implement CAPTCHA to prevent abuse
- Add email verification for user registration
- Log all password reset attempts for security auditing
- Consider using a separate database table for reset tokens

## Gmail SMTP Settings

- **Host**: smtp.gmail.com
- **Port**: 587
- **Encryption**: TLS (STARTTLS)
- **Authentication**: Required
- **Username**: Your full Gmail address
- **Password**: Your App Password (not your regular password)

## Support

If you continue to experience issues:
1. Check `pages/send_reset_email.log` for detailed error logs
2. Enable PHP error reporting in development
3. Test SMTP connection using PHPMailer's test script
4. Verify your hosting provider allows outgoing SMTP connections on port 587

## Notes

- The reset link is valid for 30 minutes
- Only one active reset token per user at a time
- Old tokens are overwritten when a new reset is requested
- Tokens are cleared after successful password reset
- The feature requires the `users` table to have specific columns (see Step 1)
