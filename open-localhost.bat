@echo off
setlocal
set "APP_DIR=%~nx0"
for %%I in ("%~dp0.") do set "APP_DIR=%%~nxI"
start "" "http://localhost/%APP_DIR%/index.php"
endlocal
