@echo off
title Maria Tutor - Seed Database
cls
echo =====================================================================
echo    Reseeding Burewala Catalogs, Adjacencies, and Demo Accounts
echo =====================================================================
echo.
py seed_burewala.py
echo.
echo Seeding complete!
pause
