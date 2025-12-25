@echo off
echo ========================================
echo Mobile Invoice APK Build Script
echo ========================================
echo.

cd /d "%~dp0"

echo Checking for Android Studio...
if not exist "C:\Program Files\Android\Android Studio" (
    echo Android Studio not found in default location.
    echo Please install Android Studio first.
    pause
    exit /b 1
)

echo.
echo Build options:
echo 1. Build with Android Studio (Recommended)
echo 2. Try Cordova build
echo 3. Check build status
echo.

set /p choice="Choose option (1-3): "

if "%choice%"=="1" (
    echo.
    echo To build with Android Studio:
    echo 1. Open Android Studio
    echo 2. File -^> Open -^> Select 'simple-mobile-app' folder
    echo 3. Wait for project to sync
    echo 4. Build -^> Make Project
    echo 5. Build -^> Build APK
    echo.
    echo APK will be created at: simple-mobile-app\app\build\outputs\apk\debug\
    echo.
    pause
) else if "%choice%"=="2" (
    echo.
    echo Building with Cordova...
    cd mobile-invoice
    cordova build android
) else if "%choice%"=="3" (
    echo.
    echo Checking for existing APKs...
    dir /s /b *.apk 2>nul
    if errorlevel 1 (
        echo No APK files found.
        echo Build may still be in progress or failed.
    )
    echo.
    pause
) else (
    echo Invalid choice.
    pause
)