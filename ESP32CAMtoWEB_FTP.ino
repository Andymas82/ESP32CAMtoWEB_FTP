#include "Arduino.h"
#include <WiFi.h>
#include <WiFiClient.h>
#include "esp_camera.h"
#include "ESP32_FTPClient.h"

// Wi-Fi
char ssid[] = "Your WIFI SSID";
char pass[] = "Your WIFI PASSWORD";

// FTP
ESP32_FTPClient ftp("YOUR FTP SERVER", "YOUR FTP USER ID", "YOUR FTP PASSWORD");

// Camera
camera_fb_t *fb = NULL;
String pic_name = "image.jpg";

void setup()
{

  Serial.begin(115200);
  Serial.setDebugOutput(true);

  // Cam
  camera_config_t config;
  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer = LEDC_TIMER_0;
  config.pin_d0 = 5;
  config.pin_d1 = 18;
  config.pin_d2 = 19;
  config.pin_d3 = 21;
  config.pin_d4 = 36;
  config.pin_d5 = 39;
  config.pin_d6 = 34;
  config.pin_d7 = 35;
  config.pin_xclk = 0;
  config.pin_pclk = 22;
  config.pin_vsync = 25;
  config.pin_href = 23;
  config.pin_sscb_sda = 26;
  config.pin_sscb_scl = 27;
  config.pin_pwdn = 32;
  config.pin_reset = -1;
  config.xclk_freq_hz = 20000000;
  config.pixel_format = PIXFORMAT_JPEG;
  config.frame_size = FRAMESIZE_QQVGA; 
  config.jpeg_quality = 10;          // JPEG quality
  config.fb_count = 2;

  // Cam init
  esp_err_t err = esp_camera_init(&config);
  if (err != ESP_OK) {
    Serial.print("Error: 0x");
    Serial.println(err, HEX);
    return;
  }

  // Wi-Fi connect
  WiFi.begin(ssid, pass);
  Serial.println("\nConnecting to Wi-Fi");

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("Wi-Fi connected!");
    Serial.println(WiFi.localIP());
  }
}

void loop()
{
  //  Capture
  if (take_picture()) {
    // Uploading
    FTP_upload();
  } else {
    Serial.println("Unable to capture image.");
  }

  delay(500);  // delay
}

// Picture capture
bool take_picture()
{
  Serial.println("Capturing...");
  
  fb = esp_camera_fb_get();
  if (!fb) {
    Serial.println("Error..");
    return false;
  }

  // Creating a file
  pic_name = "image.jpg";
  Serial.println("Image saved as: " + pic_name);

  return true;
}

// FTP upload
void FTP_upload()
{
  Serial.println("Uploading...");

  ftp.OpenConnection();
  ftp.InitFile("Type I");

  // your path
  ftp.ChangeWorkDir("htdocs/");

  // Create a new file
  const char *f_name = pic_name.c_str();
  ftp.NewFile(f_name);

  // Rewrite a file
  ftp.WriteData(fb->buf, fb->len);

  // Close
  ftp.CloseFile();

  Serial.println("image uploaded successfully");

  // Free up memory
  esp_camera_fb_return(fb);
}
