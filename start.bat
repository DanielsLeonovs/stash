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

REM Ja root lietotajam MySQL ir uzlikta parole, ieraksti to sheit.
REM Ja paroles nav (XAMPP noklusejums), atstaj tukshu starp pedinam.
set DB_USER=root
set DB_PASS=

REM db.php lasa shos mainigos no vides (getenv), bet tie ir redzami tikai
REM PHP serverim, kuru palaizham SHAJA pashA cmd sesija (sk. beigas).

if not exist "%PHP%" (
    echo [KLUDA] Neatradu %PHP%
    echo Parbaudi, vai XAMPP ir instalets un vai PHP mainigais augsa norada pareizu celu.
    pause
    exit /b 1
)

echo === Parbaudu, vai MySQL atbild uz porta %DB_PORT% (lietotajs %DB_USER%) ===
"%MYSQL%" -u %DB_USER% --port=%DB_PORT% --password=%DB_PASS% -e "SELECT 1;" >nul 2>nul
if errorlevel 1 (
    echo [KLUDA] Neizdevas pieslegties MySQL uz porta %DB_PORT% ar lietotaju %DB_USER%.
    echo Iespejamie celogi:
    echo   1. Root lietotajam IR parole, bet DB_PASS augsa sata skripta ir tukss - ieraksti pareizo paroli.
    echo   2. MySQL Control Panel darbojas uz cita porta - salidzini ar DB_PORT so skripta.
    echo   3. MySQL vispar nav iedarbinats - atver XAMPP Control Panel un uzspied "Start" pie MySQL.
    pause
    exit /b 1
)

echo === Importeju schema.sql (dross palaist atkartoti - tabulas tiek izveidotas no jauna) ===
"%MYSQL%" -u %DB_USER% --port=%DB_PORT% --password=%DB_PASS% < schema.sql
if errorlevel 1 (
    echo [BRIDINAJUMS] Neizdevas importet schema.sql automatiski.
    echo Importe to pats caur http://localhost/phpmyadmin.
)

echo === Palaizu PHP serveri ar %PHP% (DB_PORT=%DB_PORT%) ===
start "" http://localhost:8000/home.html
"%PHP%" -S localhost:8000 router.php
