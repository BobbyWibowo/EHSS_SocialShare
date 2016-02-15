@echo off

REM Package files excluding git-related folders and files with 7-Zip command line (assuming 7-Zip is installed on C:\Program Files\7-Zip)

"C:\Program Files\7-Zip\7z.exe" a -x!.git -x!archive.bat -x!.gitignore addon-EndlessHorizonSocialShare.zip
echo.
pause
