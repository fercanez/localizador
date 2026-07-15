@echo off
setlocal
set ROOT=%~dp0
set TOOLS=%ROOT%.tools
set JAVA_HOME=%TOOLS%\jdk-17
set ANDROID_HOME=%TOOLS%\android-sdk
set ANDROID_SDK_ROOT=%ANDROID_HOME%
set GRADLE_OPTS=-Djavax.net.ssl.trustStore=%TOOLS%\gradle-truststore.jks -Djavax.net.ssl.trustStorePassword=changeit
set JAVA_TOOL_OPTIONS=-Djavax.net.ssl.trustStore=%TOOLS%\gradle-truststore.jks -Djavax.net.ssl.trustStorePassword=changeit
set PATH=%JAVA_HOME%\bin;%ANDROID_HOME%\platform-tools;%PATH%

cd /d "%ROOT%android"
call gradlew.bat assembleDebug --no-daemon
if errorlevel 1 exit /b 1

copy /Y "%ROOT%android\app\build\outputs\apk\debug\app-debug.apk" "%ROOT%CatastroMexicali-debug.apk"
echo.
echo APK listo:
echo %ROOT%CatastroMexicali-debug.apk
endlocal
