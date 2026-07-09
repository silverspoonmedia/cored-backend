<?php

$root = dirname(__DIR__);
$factoryMap = [
    'app/Domain/Identity/Models/User.php' => 'UserFactory',
    'app/Domain/Playground/Models/Order.php' => 'OrderFactory',
    'app/Domain/Playground/Models/OrderDetail.php' => 'OrderDetailFactory',
    'app/Domain/Playground/Models/Attachment.php' => 'AttachmentFactory',
    'app/Domain/Playground/Models/AttachmentUpload.php' => 'AttachmentUploadFactory',
    'app/Domain/Notification/Models/ActivityNotification.php' => 'ActivityNotificationFactory',
    'app/Domain/Notification/Models/NotificationTypeSetting.php' => 'NotificationTypeSettingFactory',
];

foreach ($factoryMap as $relativePath => $factoryClass) {
    $file = $root.'/'.$relativePath;

    if (! is_file($file)) {
        fwrite(STDERR, "Skip missing model: {$relativePath}\n");
        continue;
    }

    $content = file_get_contents($file);

    if (str_contains($content, 'protected static function newFactory')) {
        echo "Already has newFactory: {$relativePath}\n";
        continue;
    }

    if (! str_contains($content, 'use Database\\Factories\\'.$factoryClass)) {
        $content = preg_replace(
            '/(namespace [^;]+;\R)/',
            '$1'."\nuse Database\\Factories\\{$factoryClass};\n",
            $content,
            1
        );
    }

    $method = <<<PHP

    /**
     * @return {$factoryClass}
     */
    protected static function newFactory(): {$factoryClass}
    {
        return {$factoryClass}::new();
    }

PHP;

    $content = preg_replace(
        '/(use HasFactory[^;]+;\R)/',
        '$1'.$method,
        $content,
        1
    );

    if (! str_contains($content, 'protected static function newFactory')) {
        $content = preg_replace(
            '/(use HasActivity, HasFactory[^;]+;\R)/',
            '$1'.$method,
            $content,
            1
        );
    }

    file_put_contents($file, $content);
    echo "Added newFactory to {$relativePath}\n";
}

echo "Done.\n";
