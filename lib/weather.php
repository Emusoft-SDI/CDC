<?php
// lib/weather.php
function getWeatherForecast($lat = 9.0820, $lng = 8.6753) {
    $apiKey = 'YOUR_OPENWEATHER_API_KEY';
    $url = "https://api.openweathermap.org/data/2.5/forecast?lat={$lat}&lon={$lng}&appid={$apiKey}&units=metric";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

function getWeatherForecastSummary() {
    $forecast = getWeatherForecast();
    if (!$forecast || !isset($forecast['list'])) {
        return "Weather data unavailable.";
    }
    
    // Analyze next 5 days
    $rainDays = 0;
    $highTemp = 0;
    $lowTemp = 40;
    
    foreach (array_slice($forecast['list'], 0, 40) as $item) { // 8 items per day × 5 days
        if (isset($item['rain']['3h']) && $item['rain']['3h'] > 5) {
            $rainDays++;
        }
        $highTemp = max($highTemp, $item['main']['temp_max']);
        $lowTemp = min($lowTemp, $item['main']['temp_min']);
    }
    
    $rainImpact = $rainDays > 2 ? "Heavy rain expected - may impact field visits." : 
                  ($rainDays > 0 ? "Light rain possible." : "Clear conditions expected.");
    
    return "Temperature: {$lowTemp}°C - {$highTemp}°C. {$rainImpact}";
}
?>