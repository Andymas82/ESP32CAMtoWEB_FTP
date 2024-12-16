<?php
// AWS Credentials
$awsKey = "";      // Your AWS Access Key
$awsSecret = "";  // Your AWS Secret Key
$region = "eu-west-2";               // Your region AWS
$service = "rekognition";            // AWS service

// image path
$imagePath = "image.jpg";

if (file_exists($imagePath)) {
    $imageData = file_get_contents($imagePath);
    $endpoint = "https://rekognition.$region.amazonaws.com";

    function createAwsRequest($target, $payload, $region, $service, $awsKey, $awsSecret) {
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        $headers = [
            "content-type: application/x-amz-json-1.1",
            "host: rekognition.$region.amazonaws.com",
            "x-amz-date: $timestamp",
            "x-amz-target: $target"
        ];

        $canonicalHeaders = "content-type:application/x-amz-json-1.1\nhost:rekognition.$region.amazonaws.com\nx-amz-date:$timestamp\nx-amz-target:$target\n";
        $signedHeaders = "content-type;host;x-amz-date;x-amz-target";
        $payloadHash = hash('sha256', $payload);

        $canonicalRequest = "POST\n/\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

        $credentialScope = "$date/$region/$service/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n$timestamp\n$credentialScope\n" . hash('sha256', $canonicalRequest);

        $kSecret = "AWS4" . $awsSecret;
        $kDate = hash_hmac('sha256', $date, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', "aws4_request", $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorizationHeader = "AWS4-HMAC-SHA256 Credential=$awsKey/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

        $headers[] = "Authorization: $authorizationHeader";

        return [$headers, $payload];
    }

    //  DetectFaces request
    $detectFacesPayload = json_encode([
        "Image" => ["Bytes" => base64_encode($imageData)],
        "Attributes" => ["ALL"]
    ]);
    list($detectFacesHeaders, $detectFacesPayload) = createAwsRequest("RekognitionService.DetectFaces", $detectFacesPayload, $region, $service, $awsKey, $awsSecret);

    // DetectLabels request
    $detectLabelsPayload = json_encode([
        "Image" => ["Bytes" => base64_encode($imageData)],
        "MaxLabels" => 10,
        "MinConfidence" => 80
    ]);
    list($detectLabelsHeaders, $detectLabelsPayload) = createAwsRequest("RekognitionService.DetectLabels", $detectLabelsPayload, $region, $service, $awsKey, $awsSecret);

    // request processing
    function executeAwsRequest($endpoint, $headers, $payload) {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo "Error: " . curl_error($ch);
            curl_close($ch);
            return null;
        } else {
            curl_close($ch);
            return json_decode($response, true);
        }
    }

    $faceResult = executeAwsRequest($endpoint, $detectFacesHeaders, $detectFacesPayload);
    $labelResult = executeAwsRequest($endpoint, $detectLabelsHeaders, $detectLabelsPayload);

    //  HTML w/ results
    echo "<html><body>";
    echo "<h1>Image Analysis Results</h1>";
    echo "<img src='data:image/jpeg;base64," . base64_encode($imageData) . "' alt='Uploaded Image' /><br><br>";

    if (!empty($faceResult['FaceDetails'])) {
        $face = $faceResult['FaceDetails'][0];

        echo "<h3>Detected Face Information</h3>";
        echo "<p><strong>Gender:</strong> " . $face['Gender']['Value'] . "</p>";
        echo "<p><strong>Age Range:</strong> From " . $face['AgeRange']['Low'] . " to " . $face['AgeRange']['High'] . "</p>";

        echo "<h4>Emotions Detected:</h4>";
        echo "<ul>";
        foreach ($face['Emotions'] as $emotion) {
            if ($emotion['Confidence'] >= 10) {
                echo "<li>" . $emotion['Type'] . " (" . round($emotion['Confidence'], 2) . "%)</li>";
            }
        }
        echo "</ul>";

        if (isset($face['Sunglasses']) && $face['Sunglasses']['Value'] == true) {
            echo "<p><strong>Wearing Sunglasses:</strong> Yes</p>";
        }

        if (isset($face['Eyeglasses']) && $face['Eyeglasses']['Value'] == true) {
            echo "<p><strong>Wearing Eyeglasses:</strong> Yes</p>";
        }
    } else {
        echo "<p>No face detected.</p>";
    }

    if (!empty($labelResult['Labels'])) {
        echo "<h3>Detected Objects:</h3>";
        echo "<ul>";
        $phoneDetected = false;
        foreach ($labelResult['Labels'] as $label) {
            echo "<li>" . $label['Name'] . " (" . round($label['Confidence'], 2) . "%)</li>";
            if (in_array($label['Name'], ['Cell Phone', 'Mobile Phone']) && $label['Confidence'] >= 80) {
                $phoneDetected = true;
            }
        }
        echo "</ul>";

        if ($phoneDetected) {
            echo "<p><strong>Phone detected:</strong> Yes</p>";
        } else {
            echo "<p><strong>Phone detected:</strong> No</p>";
        }
    } else {
        echo "<p>No objects detected.</p>";
    }

    echo "</body></html>";
} else {
    echo "<html><body><h1>No image found in the specified folder.</h1></body></html>";
}
?>
