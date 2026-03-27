<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4f46e5;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .info-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-table td:first-child {
            font-weight: bold;
            color: #555;
            width: 40%;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Database Backup</h1>
    </div>

    <div class="content">
        <p>Your scheduled database backup has completed successfully.</p>

        <table class="info-table">
            <tr>
                <td>Date</td>
                <td>{{ $date }}</td>
            </tr>
            <tr>
                <td>Database</td>
                <td>{{ $database }}</td>
            </tr>
            <tr>
                <td>File Size</td>
                <td>{{ $fileSize }}</td>
            </tr>
            <tr>
                <td>Filename</td>
                <td>{{ $filename }}</td>
            </tr>
        </table>

        <p>The backup file is attached to this email.</p>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} Surprise moi. All rights reserved.</p>
    </div>
</body>
</html>
