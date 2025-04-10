@php
    $title = 'License Validation Failed';
    $supportUrl = $support_url ?? 'https://www.jadesdev.com.ng/support';
    $daysElapsed = $days_elapsed ?? 'multiple';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        :root {
            --primary-color: #4A6CFA;
            --danger-color: #DC3545;
            --dark-color: #212529;
            --light-color: #F8F9FA;
            --gray-color: #6C757D;
            --border-color: #DEE2E6;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background-color: #f5f7fb;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .container {
            max-width: 800px;
            width: 100%;
            margin: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            background-color: white;
            overflow: hidden;
        }
        
        .header {
            background-color: var(--danger-color);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .content {
            padding: 30px;
        }
        
        .info-box {
            background-color: #e9ecef;
            border-radius: 5px;
            padding: 15px;
            margin-top: 25px;
        }
        
        .action-box {
            background-color: #f8f9fa;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin: 25px 0;
        }
        
        .btn {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 5px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #3A5CDA;
        }
        
        h2 {
            margin-top: 0;
        }
        
        .text-danger {
            color: var(--danger-color);
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: var(--gray-color);
            border-top: 1px solid var(--border-color);
            background-color: #f8f9fa;
        }
        
        .steps {
            list-style-type: none;
            padding: 0;
            counter-reset: steps;
        }
        
        .steps li {
            position: relative;
            padding-left: 50px;
            margin-bottom: 20px;
            counter-increment: steps;
        }
        
        .steps li::before {
            content: counter(steps);
            position: absolute;
            left: 0;
            top: 0;
            width: 30px;
            height: 30px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>⚠️ License Validation Failed</h2>
        </div>
        
        <div class="content">
            <h3>Access Restricted</h3>
            <p>
                The license for <strong>Foundation</strong> on this domain (<strong>{{ $domain }}</strong>) 
                could not be validated. Your access has been restricted because it has been 
                <span class="text-danger">{{ $days_elapsed }}</span> days since the last successful validation.
            </p>
            
            <div class="action-box">
                <h4>How to Fix This Issue:</h4>
                <ul class="steps">
                    <li>
                        <strong>Check your internet connection</strong><br>
                        Ensure your server has proper connectivity to validate the license.
                    </li>
                    <li>
                        <strong>Verify your ACCESS_KEY</strong><br>
                        Make sure your <code>ACCESS_KEY</code> environment variable is set correctly.
                    </li>
                    <li>
                        <strong>Check license status</strong><br>
                        Verify your license is active and hasn't expired.
                    </li>
                    <li>
                        <strong>Contact support</strong><br>
                        If you continue experiencing issues, please reach out to our support team.
                    </li>
                </ul>
            </div>
            
            <div class="text-center" style="text-align: center; margin-top: 30px;">
                <a href="{{ $supportUrl }}" class="btn">Contact Support</a>
            </div>
            
            <div class="info-box">
                <h4>Troubleshooting Information:</h4>
                <p><strong>Domain:</strong> {{ $domain }}</p>
                <p><strong>Access Key:</strong> {{ substr($access_key, 0, 8) }}...</p>
                <p><strong>Days Since Last Validation:</strong> {{ $days_elapsed }}</p>
            </div>
        </div>
        
        <div class="footer">
            &copy; {{ date('Y') }} Jadesdev Foundation. All rights reserved.
        </div>
    </div>
</body>
</html>