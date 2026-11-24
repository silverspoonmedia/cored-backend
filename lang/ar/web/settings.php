<?php

return [
    'title' => 'الإعدادات',
    'subtitle' => 'إعدادات تشغيلية تُدار عبر واجهة ال�
شرف.',
    'read_only' => 'لديك صلاحية عرض هذه الإعدادات فقط.',
    'source' => 'ال�
صدر الحالي: :source',
    'groups' => [
        'platform' => 'ال�
نصة',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        // curated at 2026-09-22T20:29:07+07:00 — ar curation wave
        'notification_audiences' => 'ج�
هور الإشعارات',
        'currencies' => 'الع�
لات',
    ],
    'fields' => [
        // Platform — JWT
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'platform.jwt.ttl' => '�
دة صلاحية ر�
ز الوصول JWT (بالدقائق)',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'platform.jwt.refresh_ttl' => 'نافذة تجديد JWT (بالدقائق)',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'platform.jwt.remember_ttl' => '�
دة صلاحية خيار «تذكرني» لـ JWT (بالدقائق)',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'platform.jwt.refresh_skew' => 'ها�
ش التجديد الصا�
ت لـ JWT (بالدقائق)',
        'platform.jwt.blacklist_grace_period' => 'فترة الس�
اح لقائ�
ة حظر JWT (بالثواني)',
        // Platform — auth / 2FA
        'platform.auth.default_role' => 'الدور الافتراضي لل�
ستخد�
ين الجدد',
        'platform.auth.verification_expire' => 'انتهاء صلاحية رابط التحقق �
ن البريد الإلكتروني (بالدقائق)',
        'platform.two_factor.window' => 'نافذة التحقق �
ن ر�
وز TOTP لل�
صادقة الثنائية',
        'platform.two_factor.issuer' => 'اس�
 جهة إصدار ال�
صادقة الثنائية (تطبيق ال�
صادقة)',
        // Platform — hCaptcha
        'platform.hcaptcha.enabled' => 'تفعيل hCaptcha',
        // Platform — notifications
        'platform.notifications.delivery' => 'طريقة تسلي�
 الإشعارات',
        'platform.notifications.polling_interval' => 'الفاصل الز�
ني لاستطلاع الإشعارات (بالثواني)',
        // Platform — error log
        'platform.errorlog.enabled' => 'تفعيل سجل الأخطاء الدائ�
',
        'platform.errorlog.clean_after_days' => '�
دة الاحتفاظ بسجل الأخطاء (بالأيا�
)',
        'platform.errorlog.max_message_length' => 'الحد الأقصى لطول رسالة الخطأ',
        'platform.errorlog.max_trace_length' => 'الحد الأقصى لطول تتبع الخطأ',
        'platform.errorlog.max_context_json_length' => 'الحد الأقصى لطول JSON لسياق الخطأ',
        // Platform — activity log
        'platform.activitylog.enabled' => 'تفعيل سجل النشاط',
        'platform.activitylog.buffer_enabled' => 'تفعيل التخزين ال�
ؤقت لسجل النشاط',
        // Platform — localization
        'platform.localization.locale_cookie' => 'اس�
 �
لف تعريف ارتباط اللغة',
        'identity.discord.connections_snapshot_max_age_minutes' => 'الحد الأقصى لع�
ر لقطة اتصالات Discord (بالدقائق)',
        'platform.localization.locale_cookie_minutes' => '�
دة �
لف تعريف ارتباط اللغة (بالدقائق)',
        // Platform — payment
        // curated at 2026-09-22T20:27:19+07:00 — ar curation wave
        'platform.payment.channel_fee_bearer' => 'الطرف الذي يتح�
ل رسو�
 قناة الدفع',
        'platform.payment.reporting_timezone' => 'ال�
نطقة الز�
نية لتقارير الدفع',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'platform.payment.prefer_channel_checkout' => 'تفضيل إت�
ا�
 الشراء الأصلي للقناة',
        'platform.payment.default_buyer_phone' => 'هاتف ال�
شتري الافتراضي (بديل iPaymu)',
        // Platform — mail identity
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'platform.mail.from_address' => 'عنوان ال�
رسل للبريد الصادر',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'platform.mail.from_name' => 'اس�
 ال�
رسل للبريد الصادر',
        // Platform — service
        'platform.service.grace_days' => 'أيا�
 الس�
اح للخد�
ة',
        'platform.service.settlement_currency' => 'ع�
لة التسوية',
        'platform.service.purchase_max_quantity' => 'الحد الأقصى لك�
ية شراء الخد�
ة',
        // Platform — inference fallbacks
        'platform.inference.default_provider_slug' => '�
عرّف �
زود الاستدلال الافتراضي',
        'platform.inference.default_input_per_million_usd' => 'سعر الإدخال الافتراضي (بالدولار لكل �
ليون ر�
ز)',
        'platform.inference.default_output_per_million_usd' => 'سعر الإخراج الافتراضي (بالدولار لكل �
ليون ر�
ز)',
        'platform.inference.unknown_input_per_million_usd' => 'سعر إدخال الن�
وذج غير ال�
عروف (بالدولار لكل �
ليون ر�
ز)',
        'platform.inference.unknown_output_per_million_usd' => 'سعر إخراج الن�
وذج غير ال�
عروف (بالدولار لكل �
ليون ر�
ز)',
        // Platform — Frankfurter FX
        'platform.frankfurter.cache_ttl' => 'نافذة حداثة أسعار صرف Frankfurter (بالثواني)',
        'platform.frankfurter.timeout' => '�
هلة طلبات أسعار الصرف إلى Frankfurter (بالثواني)',
 ال�
لف (بالبايت)',
لفات في الدفعة',
ليات الرفع ال�
تزا�
نة',
تعددة الأجزاء ال�
تزا�
نة',
 جزء الرفع (بالبايت)',
تعدد الأجزاء (بالبايت)',
وقعة �
سبقًا في الاستجابة',
دة صلاحية عنوان URL ال�
وقّع �
سبقًا (بالدقائق)',
دة صلاحية جلسة الرفع (بالساعات)',
اح للرفع القدي�
 (بالساعات)',
هلة عد�
 نشاط الجزء اليتي�
 (بالدقائق)',
ساحات الع�
ل ال�
�
لوكة الأساسية',
ناطق التخزين ال�
عطلة',
دة صلاحية عنوان URL للتنزيل ال�
وقّع (بالدقائق)',
ش ذاكرة التخزين ال�
ؤقت للتنزيل ال�
وقّع (بالثواني)',
دة صلاحية توقيع التنزيل العا�
 (بالدقائق)',
س�
وح بها',
جانية اليو�
ية',
جانية اليو�
ية لكل �
ستخد�
',
نطقة الز�
نية لإعادة ضبط التنزيل ال�
جاني اليو�
ي',
عا�
ل زيادة فوترة الذكاء الاصطناعي',
يكرو-دولار لكل أصل',
عا�
ل تقدير �
حاولات التفكير',
 صورة الطلب (بالبايت)',
حاولة طلبات الذكاء الاصطناعي',
حاولات الرؤية في �
رحلة التفكير',
 جزء دفعة الذكاء الاصطناعي',
لفات دفعة الذكاء الاصطناعي',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
رحلة الذكاء الاصطناعي: التحول إلى ال�
عالجة العادية بعد (بالثواني)',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
رحلة الذكاء الاصطناعي: بدء �
عالجة فترة الس�
اح بعد (بالثواني)',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
رحلة الذكاء الاصطناعي: دخول الرؤية وضع الس�
اح بعد (بالثواني)',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
رحلة الذكاء الاصطناعي: دخول التفكير وضع الس�
اح بعد (بالثواني)',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
رحلة الذكاء الاصطناعي: انتقال �
ها�
 التفكير إلى قائ�
ة الانتظار ال�
تراك�
ة بعد (بالثواني)',
 صورة الرؤية',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
تغيّر صورة الرؤية',
دة صلاحية عنوان URL ال�
وقّع لصورة الرؤية (بالدقائق)',
عرّف �
زود استدلال Microstock',
وذج الرؤية',
وذج ال�
ستوى ال�
جاني للرؤية (فارغ = الن�
وذج ال�
دفوع)',
هلة HTTP للرؤية (بالثواني)',
وذج التفكير',
وذج ال�
ستوى ال�
جاني للتفكير (فارغ = الن�
وذج ال�
دفوع)',
هلة HTTP للتفكير (بالثواني)',
يزانية الر�
وز لل�
زود الخارجي (بالثواني)',
يزانية الر�
وز لل�
زود الخارجي لكل نافذة',
تزا�
نة',
تزا�
نة',
ن الرؤية لل�
ج�
وعة',
ن التفكير لل�
ج�
وعة',
ن الرؤية الفردية',
ن التفكير الفردي',
زود الخارجي بعد (بالثواني)',
    ],
    'hints' => [
        'platform.mail.from_address' => 'عنوان ال�
رسل العا�
 للبريد الصادر (تبقى بيانات اعت�
اد النقل في .env).',
        'platform.payment.default_buyer_phone' => 'رق�
 بديل عند�
ا لا يحتوي �
لف ال�
شتري على هاتف (�
تطلب iPaymu).',
        'platform.frankfurter.timeout' => '�
هلة طلبات أسعار الصرف إلى Frankfurter عبر HTTP.',
وذج الرؤية ال�
جاني؛ القي�
ة الفارغة ترث ن�
وذج الرؤية ال�
دفوع.',
وذج التفكير ال�
جاني؛ القي�
ة الفارغة ترث ن�
وذج التفكير ال�
دفوع.',
عرّف اتصال الذكاء الاصطناعي (الإعدادات ← اتصالات الذكاء الاصطناعي) ل�
رحلة الرؤية؛ فارغ يستخد�
 الاتصال الافتراضي.',
عرّف اتصال الذكاء الاصطناعي (الإعدادات ← اتصالات الذكاء الاصطناعي) ل�
رحلة التفكير؛ فارغ يستخد�
 الاتصال الافتراضي.',
رجعية ل�
يزانية ر�
وز ال�
زود الخارجي (يُفرض الحد عبر قيود التزا�
ن).',
        'platform.jwt.refresh_skew' => 'حدّث ر�
ز الوصول قبل انتهاء صلاحيته بهذا العدد �
ن الدقائق.',
        // curated at 2026-09-22T20:27:19+07:00 — ar curation wave
        'platform.notifications.delivery' => 'يستخد�
 الوضع الفعلي WebSockets ويتطلب إعداد بيانات اعت�
اد PUSHER_* / البث؛ وإلا فتعود أيقونة الإشعارات إلى استطلاع HTTP.',
        // curated at 2026-09-22T20:51:39+07:00 — wave 2 follow-up: enabled label consistency + hint punctuation
        'platform.payment.channel_fee_bearer' => 'الطرف الذي يتح�
ل رسو�
 قناة الدفع: التاجر أو الع�
يل.',
        'platform.inference.default_provider_slug' => 'ال�
زود البديل عند عد�
 تحديد �
زود في الطلب.',
        'platform.inference.default_input_per_million_usd' => 'سعر فوترة �
ؤقت للن�
اذج ال�
سجلة التي لا ت�
لك سعرًا في الكتالوج.',
        'platform.inference.unknown_input_per_million_usd' => 'سعر فوترة �
ؤقت للن�
اذج غير ال�
سجلة.',
ن ال�
عالجة السريعة إلى ال�
عالجة العادية.',
عالجة فترة الس�
اح.',
رحلة الرؤية في وضع الس�
اح.',
رحلة التفكير في وضع الس�
اح.',
ها�
 التفكير إلى قائ�
ة الانتظار ال�
تراك�
ة.',
        // curated at 2026-09-27T19:30:00+07:00 — ar curation wave
        'identity.discord.connections_snapshot_max_age_minutes' => 'ال�
دة التي يُعت�
د خلالها على لقطات اتصالات Discord ال�
حفوظة قبل أن يؤدي فشل التحقق إلى تحديث �
باشر �
ن Discord API.',
    ],
    'options' => [
        'platform' => [
            'notifications' => [
                'delivery' => [
                    'polling' => 'استطلاع HTTP',
                    'realtime' => 'WebSocket (الوقت الفعلي)',
                ],
            ],
        ],
    ],
];
