@echo off
cd /d "%~dp0"
title Sauvegarde - Saint Martin de Tour
call "%~dp0outils\trouver-php.bat"
if not defined PHP (echo PHP est introuvable sur cet ordinateur. & pause & exit /b 1)
echo.
echo  Branchez la cle USB, puis tapez sa lettre (exemple : E).
set /p LETTRE= Lettre de la cle USB : 
if "%LETTRE%"=="" (echo Aucune lettre tapee. & pause & exit /b 1)
set "LETTRE=%LETTRE::=%"
"%PHP%" artisan ecole:sauvegarder %LETTRE%:\
echo.
echo  Laissez la cle branchee jusqu au message ci-dessus, puis retirez-la proprement.
pause
