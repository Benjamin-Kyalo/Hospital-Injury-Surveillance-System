@echo off
setlocal
echo Stopping the PHP server ...
taskkill /FI "WINDOWTITLE eq HISS PHP server*" /F >nul 2>&1
echo Your PostgreSQL Windows service is left running, so other projects are not affected.
echo Done.
