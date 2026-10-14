@echo off
title Maria Tutor - Burewala Tutor Marketplace
cls
echo =====================================================================
echo           MARIA TUTOR - BUREWALA TUTOR MARKETPLACE
echo =====================================================================
echo.
echo Application URL: http://127.0.0.1:5000
echo.
echo Demo Accounts:
echo   1. Super Admin: admin@mariahome.com       / Admin123
echo   2. Normal Admin: staff@mariahome.com      / Staff123
echo   3. Approved Tutor: 36402-1234567-1        / Tutor123
echo      (Or: tutor@mariahome.com or T-BW001)
echo   4. Parent: parent@mariahome.com           / Parent123
echo.
echo Starting Flask server... Press Ctrl+C to stop.
echo =====================================================================
py app.py
pause
