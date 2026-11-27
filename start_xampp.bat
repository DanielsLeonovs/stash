@echo off
REM ============================================================
REM  Stash — palaišana ar XAMPP
REM  Pieņem, ka XAMPP ir instalēts noklusējuma vietā C:\xampp
REM  Ja tavs XAMPP ir citur, nomaini zemāk esošo XAMPP mainīgo.
REM
REM  SVARĪGI: pirms šī skripta palaišanas atver XAMPP Control Panel
REM  un uzspied "Start" pie MySQL rindas. Šis skripts MySQL pats
REM  nepalaiž (lai neiekārtos, kā tas notika ar mysql_start.bat).
REM ============================================================

set XAMPP=C:\xampp
set PHP=%XAMPP%\php\php.exe
set MYSQL=%XAMPP%\mysql\bin\mysql.exe

REM XAMPP Control Panel pie tevis radija MySQL uz porta 3307 (nevis
REM standarta 3306), jo 3306 bija aizemts kadas citas programmas.
REM Ja pec laika redzi citu portu Control Panel loga, nomaini ari sito.
set DB_PORT=3307

if not exist "%PHP%" (
    echo [KLUDA] Neatradu %PHP%
    echo Parbaudi, vai XAMPP ir instalets un vai PHP mainigais augsa norada pareizu celu.
    pause
    exit /b 1
)

echo === Parbaudu, vai MySQL atbild uz porta %DB_PORT% ===
"%MYSQL%" -u root --port=%DB_PORT% -e "SELECT 1;" >nul 2>nul
if errorlevel 1 (
    echo [KLUDA] MySQL neatbild uz porta %DB_PORT%.
    echo Paskaties XAMPP Control Panel ailina "Port(s)" pie MySQL rindas un salidzini
    echo ar DB_PORT so skripta. Atver XAMPP Control Panel un uzspied "Start" pie MySQL,
    echo tad palaid so skriptu vel reiz.
    pause
    exit /b 1
)

echo === Importeju schema.sql (dross palaist atkartoti - tabulas tiek izveidotas no jauna) ===
"%MYSQL%" -u root --port=%DB_PORT% < schema.sql
if errorlevel 1 (
    echo [BRIDINAJUMS] Neizdevas importet schema.sql automatiski.
    echo Importe to pats caur http://localhost/phpmyadmin.
)

echo === Palaizu PHP serveri ar %PHP% ===
start "" http://localhost:8000/home.html
"%PHP%" -S localhost:8000 router.php
