@echo off
chcp 65001 >nul
cd /d "%~dp0"

if not exist "%~dp0data\planos\files\SP" (
  echo Copiando planos SP desde GROMA...
  powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\sync_planos_files.ps1" -Homoclave SP
  if errorlevel 1 (
    echo FALLO al copiar desde GROMA.
    pause
    exit /b 1
  )
) else (
  echo Ya hay archivos locales en data\planos\files\SP
)

if not exist "%TEMP%\mxli_planos_files_SP.zip" (
  echo Falta el zip. Ejecute scripts\sync_planos_files.ps1
  pause
  exit /b 1
)

echo.
echo === Subir al servidor (escriba la clave SSH cuando la pida^) ===
echo 1/4 PHP
scp -P 3839 "%~dp0includes\planos.php" canez@5.78.201.11:/tmp/mxli_planos.php
if errorlevel 1 goto :scpfail
scp -P 3839 "%~dp0api\planos-file.php" canez@5.78.201.11:/tmp/mxli_planos_file.php
if errorlevel 1 goto :scpfail
scp -P 3839 "%~dp0api\planos.php" canez@5.78.201.11:/tmp/mxli_api_planos.php
if errorlevel 1 goto :scpfail
scp -P 3839 "%~dp0plano.php" canez@5.78.201.11:/tmp/mxli_plano.php
if errorlevel 1 goto :scpfail
echo 2/4 zip de planos SP (58 MB, tarde 1-3 min^)
scp -P 3839 "%TEMP%\mxli_planos_files_SP.zip" canez@5.78.201.11:/tmp/mxli_planos_files.zip
if errorlevel 1 goto :scpfail
echo 3/4 instalar en el servidor
ssh -p 3839 canez@5.78.201.11 "sudo cp /tmp/mxli_planos.php /var/www/html/localizacion/includes/planos.php && sudo cp /tmp/mxli_planos_file.php /var/www/html/localizacion/api/planos-file.php && sudo cp /tmp/mxli_api_planos.php /var/www/html/localizacion/api/planos.php && sudo cp /tmp/mxli_plano.php /var/www/html/localizacion/plano.php && sudo mkdir -p /var/www/html/localizacion/data/planos/files && sudo unzip -o /tmp/mxli_planos_files.zip -d /var/www/html/localizacion/data/planos/files && sudo chown -R www-data:www-data /var/www/html/localizacion/includes/planos.php /var/www/html/localizacion/api/planos-file.php /var/www/html/localizacion/api/planos.php /var/www/html/localizacion/plano.php /var/www/html/localizacion/data/planos/files && echo OK && ls /var/www/html/localizacion/data/planos/files/SP | wc -l"

if errorlevel 1 goto :scpfail
echo.
echo Listo. En el navegador: Ctrl+F5 y abra el DWG otra vez.
pause
exit /b 0
:scpfail
echo.
echo FALLO al subir. Tiene que escribir la clave SSH de canez@5.78.201.11
pause
exit /b 1
