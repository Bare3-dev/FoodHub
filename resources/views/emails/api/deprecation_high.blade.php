<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Important: API Version Deprecation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #fd7e14; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; }
        .footer { background: #6c757d; color: white; padding: 15px; text-align: center; border-radius: 0 0 5px 5px; }
        .alert { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .button { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .important { background: #fd7e14; color: white; padding: 15px; margin: 15px 0; border-radius: 5px; text-align: center; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Important: API Version Deprecation</h1>
            <p>Action Required Soon</p>
        </div>
        
        <div class="content">
            <div class="important">
                ⚠️ API Version {{ $version }} will be sunset in {{ $days_remaining }} days!
            </div>
            
            <p>Dear {{ $user_name }},</p>
            
            <p>This is an <strong>important</strong> notification that API version <strong>{{ $version }}</strong> will be permanently discontinued on <strong>{{ $sunset_date }}</strong>.</p>
            
            <p>You have <strong>{{ $days_remaining }} days</strong> to complete your migration to the newer API version.</p>
            
            <div class="alert">
                <strong>What this means:</strong>
                <ul>
                    <li>All API calls to version {{ $version }} will stop working</li>
                    <li>Your applications may experience failures</li>
                    <li>Business operations may be disrupted</li>
                </ul>
            </div>
            
            <h3>Recommended Actions:</h3>
            <ol>
                <li><strong>Plan your migration strategy</strong></li>
                <li>Migrate to the successor version: <strong>{{ $successor_version ?? 'v2' }}</strong></li>
                <li>Test all integrations</li>
                <li>Update your client applications</li>
            </ol>
            
            @if($migration_guide)
            <p><a href="{{ $migration_guide }}" class="button">📖 View Migration Guide</a></p>
            @endif
            
            <h3>Breaking Changes:</h3>
            @if(isset($breaking_changes) && is_array($breaking_changes))
                @if(isset($breaking_changes['planned_changes']))
                    <ul>
                        @foreach($breaking_changes['planned_changes'] as $change)
                            <li>{{ $change }}</li>
                        @endforeach
                    </ul>
                @endif
                @if(isset($breaking_changes['migration_notes']))
                    <p><strong>Migration Notes:</strong> {{ $breaking_changes['migration_notes'] }}</p>
                @endif
            @else
                <p>Please review the migration guide for detailed information about breaking changes.</p>
            @endif
            
            <h3>Timeline:</h3>
            <ul>
                <li><strong>Now:</strong> Start planning and development</li>
                <li><strong>{{ $days_remaining - 30 }} days:</strong> Complete testing</li>
                <li><strong>{{ $days_remaining - 7 }} days:</strong> Deploy to production</li>
                <li><strong>{{ $sunset_date }}:</strong> Version {{ $version }} discontinued</li>
            </ul>
            
            <h3>Need Help?</h3>
            <p>If you need assistance with migration:</p>
            <ul>
                <li>📧 Contact: <strong>{{ $support_contact }}</strong></li>
                <li>📚 Documentation: <a href="{{ config('app.url') }}/api/docs">API Documentation</a></li>
                <li>💬 Support: Available during business hours</li>
            </ul>
            
            <p><strong>Please take action soon to avoid service disruption.</strong></p>
        </div>
        
        <div class="footer">
            <p>FoodHub API Team</p>
            <p>This is an automated notification. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
