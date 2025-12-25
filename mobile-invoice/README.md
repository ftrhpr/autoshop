# Mobile Invoice Android APK Build Guide

This Cordova project contains the mobile invoice application for the Auto Shop management system.

## Project Structure
- `www/` - Web assets (HTML, CSS, JS)
- `platforms/android/` - Android platform files
- `config.xml` - Cordova configuration

## Features
- WebView-based mobile app that loads the mobile invoice interface
- Camera access for photo uploads
- File system access
- Network connectivity to server

## Server Configuration
Before building, update the server URL in `www/index.html`:

```javascript
// For Android emulator
var serverUrl = 'http://10.0.2.2:8000/mobile_invoice.php';

// For physical device or production server
var serverUrl = 'https://your-server.com/mobile_invoice.php';
```

## Build Requirements
- Node.js (installed)
- Java JDK 11+ (installed - OpenJDK 25 detected)
- Android SDK (detected at C:\Users\nbika\AppData\Local\Android\sdk)
- Gradle (will be downloaded automatically)

## Building the APK

### Method 1: Using Cordova CLI (Recommended)
```bash
cd mobile-invoice
cordova build android
```

### Method 2: Using Gradle Directly
```bash
cd platforms/android
./gradlew assembleDebug
```

## Output Locations
- Debug APK: `platforms/android/app/build/outputs/apk/debug/app-debug.apk`
- Release APK: `platforms/android/app/build/outputs/apk/release/app-release.apk`

## Installation
1. Transfer the APK file to your Android device
2. Enable "Install from unknown sources" in Android settings
3. Install the APK
4. Launch the "Mobile Invoice" app

## Server Setup
To use the mobile app, you need a web server running the PHP application:

1. Set up a web server (Apache/Nginx) with PHP
2. Deploy the autoshop files
3. Ensure the server is accessible from mobile devices
4. Update the server URL in the app

## Troubleshooting
- If build fails, ensure Android SDK build tools are installed
- For network issues, check firewall settings
- Camera permissions are already configured in the app

## Development
To modify the app:
1. Edit files in `www/` directory
2. Rebuild: `cordova build android`
3. Reinstall on device

## Security Notes
- The app uses WebView to load content from your server
- Ensure your server uses HTTPS in production
- Configure proper authentication and session management</content>
<parameter name="filePath">c:\Users\nbika\Downloads\autoshop\mobile-invoice\README.md