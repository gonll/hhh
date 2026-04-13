@echo off
chcp 65001 >nul
echo.
echo ============================================================
echo  ERROR 1130: primero segui PASOS_ERROR_1130_MARIADB.txt
echo  (activar skip-grant-tables en my.ini y reiniciar MySQL).
echo ============================================================
echo.
echo Luego, con MySQL en marcha CON skip-grant-tables, ejecuta:
echo   C:\xampp\mysql\bin\mysql.exe -u root ^< "%~dp0reparar_despues_skip_grant.sql"
echo.
set MYSQL=C:\xampp\mysql\bin\mysql.exe
if not exist "%MYSQL%" (
    echo No se encontro %MYSQL%
    pause
    exit /b 1
)
echo Intentando aplicar reparar_despues_skip_grant.sql (fallara si no usaste skip-grant)...
"%MYSQL%" -u root < "%~dp0reparar_despues_skip_grant.sql"
if errorlevel 1 (
    echo.
    echo Si ves ERROR 1130 otra vez: abri PASOS_ERROR_1130_MARIADB.txt y usa Opcion A.
    pause
    exit /b 1
)
echo OK. Quita skip-grant-tables de my.ini, reinicia MySQL y probá el sistema.
pause
