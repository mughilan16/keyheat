@echo off
echo Starting Python server...
cd tracker
call .\venv\Scripts\activate
start "Python Server" cmd /k "python main.py"

echo Waiting for Python server to start...
timeout /t 3 /nobreak >nul

echo Starting PHP server...
cd ..
cd report
start "PHP Server" cmd /k "php -S localhost:8000"

echo Both servers are starting in separate windows.
echo Press any key to close this launcher...
pause >nul