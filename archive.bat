@echo off

REM Delete previous archive if exist
IF EXIST addon-EndlessHorizonSocialShare.zip del /F addon-EndlessHorizonSocialShare.zip

REM Package files excluding git-related folders and files with 7-Zip command line (assuming 7-Zip is installed on C:\Program Files\7-Zip)
"C:\Program Files\7-Zip\7z.exe" a -x!.git* -x!archive.bat addon-EndlessHorizonSocialShare.zip
echo.
pause