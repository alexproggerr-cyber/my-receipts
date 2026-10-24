@echo off
chcp 65001 >nul
cd /d "%~dp0"
for %%i in (php.exe) do set "PHPDIR=%%~dp$PATH:i"
if "%PHPDIR%"=="" (
    echo PHP не найден. Установите PHP и добавьте его в PATH.
    pause
    exit /b 1
)
echo Сайт: http://localhost:8000
echo Админка: http://localhost:8000/admin/
echo Остановить: Ctrl+C
php -d extension_dir="%PHPDIR%ext" -d extension=pdo_sqlite -d upload_max_filesize=6M -d post_max_size=8M -S localhost:8000 router.php
