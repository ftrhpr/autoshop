# Mobile Invoice Android APK - Build Instructions

## 🚀 Quick Build with Android Studio

### Prerequisites
- Android Studio installed
- Android SDK (API 33 recommended)
- Java JDK 11+

### Steps to Build APK

1. **Open Android Studio**
2. **Import Project**: File → Open → Select `simple-mobile-app` folder
3. **Wait for Gradle Sync** (may take a few minutes)
4. **Update Server URL** (see below)
5. **Build APK**:
   - Build → Make Project (Ctrl+F9)
   - Build → Build Bundle(s)/APK(s) → Build APK(s)
6. **Find APK**: `simple-mobile-app/app/build/outputs/apk/debug/app-debug.apk`

## ⚙️ Configuration

### Update Server URL
Edit `simple-mobile-app/app/src/main/java/com/autoshop/mobileinvoice/MainActivity.java`:

```java
// For Android emulator:
String serverUrl = "https://new.otoexpress.ge/mobile_invoice.php";

// For physical device with local server:
String serverUrl = "https://new.otoexpress.ge/mobile_invoice.php";

// For production server:
String serverUrl = "https://new.otoexpress.ge/mobile_invoice.php";
```

## 📱 Alternative: Cordova Build

If you prefer the Cordova project with more features:

```bash
cd mobile-invoice
cordova build android
```

## 🔧 Troubleshooting

### Gradle Download Issues
- The Cordova build downloads Gradle automatically (slow first time)
- Use Android Studio for faster builds after initial setup

### Network Issues
- Ensure your web server is accessible from mobile devices
- Check firewall settings
- For HTTPS, update AndroidManifest.xml to remove `android:usesCleartextTraffic="true"`

### Camera/File Permissions
- Permissions are already configured in AndroidManifest.xml
- App will request permissions at runtime

## 📦 APK Installation

1. Transfer APK to Android device
2. Enable "Install from unknown sources" in Settings
3. Install the APK
4. Launch "Mobile Invoice" app

## 🎯 Features Included

- ✅ WebView-based mobile app
- ✅ Camera access for photos
- ✅ File upload capabilities
- ✅ Network connectivity
- ✅ Back button navigation
- ✅ Material Design theme

## 🔄 Updating the App

1. Make changes to web files or Android code
2. Rebuild APK
3. Reinstall on device

---

**Note**: The simple-mobile-app version is a basic WebView wrapper. For advanced features like push notifications or offline support, use the Cordova project.