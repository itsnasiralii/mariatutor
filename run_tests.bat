@echo off
title Maria Tutor - Test Suite
cls
echo =====================================================================
echo    Running Automated Verification and Pytest Suites
echo =====================================================================
echo.
py test_app_verification.py
echo.
py -m pytest tests/
echo.
pause
