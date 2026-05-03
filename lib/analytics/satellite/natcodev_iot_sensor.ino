// natcodev_iot_sensor.ino
#include <WiFi.h>
#include <HTTPClient.h>
#include <Adafruit_Sensor.h>
#include <DHT.h>

// Configuration
const char* ssid = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";
const char* api_url = "https://apply.coconutventurehub.ng/api/iot/ingest.php";
const char* api_key = "YOUR_IOT_API_KEY";
const char* device_id = "NAT-FARM-001-SM01"; // Unique per device

#define DHTPIN 4
#define DHTTYPE DHT11
DHT dht(DHTPIN, DHTTYPE);

void setup() {
  Serial.begin(115200);
  dht.begin();
  
  // Connect to WiFi
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.println("Connecting to WiFi...");
  }
  Serial.println("Connected to WiFi");
}

void loop() {
  // Read sensor data
  float humidity = dht.readHumidity();
  float temperature = dht.readTemperature();
  
  if (isnan(humidity) || isnan(temperature)) {
    Serial.println("Failed to read sensor");
    delay(60000); // Wait 1 minute
    return;
  }
  
  // Send humidity data
  sendData("soil_moisture", humidity, "percent");
  
  // Send temperature data  
  sendData("temperature", temperature, "celsius");
  
  // Sleep for 30 minutes (1800000 ms)
  delay(1800000);
}

void sendData(const char* sensor_type, float value, const char* unit) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(api_url);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-API-KEY", api_key);
    
    String json = "{\"device_id\":\"" + String(device_id) + 
                  "\",\"value\":" + String(value) + 
                  ",\"unit\":\"" + String(unit) + 
                  "\",\"sensor_type\":\"" + String(sensor_type) + "\"}";
    
    int httpResponseCode = http.POST(json);
    
    if (httpResponseCode > 0) {
      Serial.println("Data sent successfully");
    } else {
      Serial.println("Error sending  " + String(httpResponseCode));
    }
    
    http.end();
  }
}