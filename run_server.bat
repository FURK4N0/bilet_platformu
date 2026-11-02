@echo off
echo ========================================================
echo  Bilet Satin Alma Platformu - Yerel Gelistirme Sunucusu
echo ========================================================
echo.
echo Sunucu baslatiliyor: http://localhost:8080
echo Tarayicinizdan http://localhost:8080 adresine gidebilirsiniz.
echo Durdurmak icin Ctrl + C tuslarina basin.
echo.

where php >nul 2>nul
if %ERRORLEVEL% equ 0 (
    php -S localhost:8080
) else if exist "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" (
    "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" -S localhost:8080
) else if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -S localhost:8080
) else (
    echo [HATA] Sisteminizde PHP bulunamadi. Lutfen PHP'yi PATH ortam degiskenine ekleyin.
    pause
)
