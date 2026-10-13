<?php

return [
    'title' => 'الإعدادات',
    'subtitle' => 'إعدادات تشغيلية تُدار عبر واجهة المشرف.',
    'read_only' => 'لديك صلاحية عرض هذه الإعدادات فقط.',
    'source' => 'المصدر الحالي: :source',
    'groups' => [
        'platform' => 'المنصة',
        'archivd' => 'Archivd',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'microstock' => 'Microstock',
        // curated at 2026-09-22T20:29:07+07:00 — ar curation wave
        'notification_audiences' => 'جمهور الإشعارات',
        'currencies' => 'العملات',
    ],
    'fields' => [
        // Platform — JWT
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'platform.jwt.ttl' => 'مدة صلاحية رمز الوصول JWT (بالدقائق)',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'platform.jwt.refresh_ttl' => 'نافذة تجديد JWT (بالدقائق)',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'platform.jwt.remember_ttl' => 'مدة صلاحية خيار «تذكرني» لـ JWT (بالدقائق)',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'platform.jwt.refresh_skew' => 'هامش التجديد الصامت لـ JWT (بالدقائق)',
        'platform.jwt.blacklist_grace_period' => 'فترة السماح لقائمة حظر JWT (بالثواني)',
        // Platform — auth / 2FA
        'platform.auth.default_role' => 'الدور الافتراضي للمستخدمين الجدد',
        'platform.auth.verification_expire' => 'انتهاء صلاحية رابط التحقق من البريد الإلكتروني (بالدقائق)',
        'platform.two_factor.window' => 'نافذة التحقق من رموز TOTP للمصادقة الثنائية',
        'platform.two_factor.issuer' => 'اسم جهة إصدار المصادقة الثنائية (تطبيق المصادقة)',
        // Platform — hCaptcha
        'platform.hcaptcha.enabled' => 'تفعيل hCaptcha',
        // Platform — notifications
        'platform.notifications.delivery' => 'طريقة تسليم الإشعارات',
        'platform.notifications.polling_interval' => 'الفاصل الزمني لاستطلاع الإشعارات (بالثواني)',
        // Platform — error log
        'platform.errorlog.enabled' => 'تفعيل سجل الأخطاء الدائم',
        'platform.errorlog.clean_after_days' => 'مدة الاحتفاظ بسجل الأخطاء (بالأيام)',
        'platform.errorlog.max_message_length' => 'الحد الأقصى لطول رسالة الخطأ',
        'platform.errorlog.max_trace_length' => 'الحد الأقصى لطول تتبع الخطأ',
        'platform.errorlog.max_context_json_length' => 'الحد الأقصى لطول JSON لسياق الخطأ',
        // Platform — activity log
        'platform.activitylog.enabled' => 'تفعيل سجل النشاط',
        'platform.activitylog.buffer_enabled' => 'تفعيل التخزين المؤقت لسجل النشاط',
        // Platform — localization
        'platform.localization.locale_cookie' => 'اسم ملف تعريف ارتباط اللغة',
        'identity.discord.connections_snapshot_max_age_minutes' => 'الحد الأقصى لعمر لقطة اتصالات Discord (بالدقائق)',
        'platform.localization.locale_cookie_minutes' => 'مدة ملف تعريف ارتباط اللغة (بالدقائق)',
        // Platform — payment
        // curated at 2026-09-22T20:27:19+07:00 — ar curation wave
        'platform.payment.channel_fee_bearer' => 'الطرف الذي يتحمل رسوم قناة الدفع',
        'platform.payment.reporting_timezone' => 'المنطقة الزمنية لتقارير الدفع',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'platform.payment.prefer_channel_checkout' => 'تفضيل إتمام الشراء الأصلي للقناة',
        'platform.payment.default_buyer_phone' => 'هاتف المشتري الافتراضي (بديل iPaymu)',
        // Platform — mail identity
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'platform.mail.from_address' => 'عنوان المرسل للبريد الصادر',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'platform.mail.from_name' => 'اسم المرسل للبريد الصادر',
        // Platform — service
        'platform.service.grace_days' => 'أيام السماح للخدمة',
        'platform.service.settlement_currency' => 'عملة التسوية',
        'platform.service.purchase_max_quantity' => 'الحد الأقصى لكمية شراء الخدمة',
        // Platform — inference fallbacks
        'platform.inference.default_provider_slug' => 'معرّف مزود الاستدلال الافتراضي',
        'platform.inference.default_input_per_million_usd' => 'سعر الإدخال الافتراضي (بالدولار لكل مليون رمز)',
        'platform.inference.default_output_per_million_usd' => 'سعر الإخراج الافتراضي (بالدولار لكل مليون رمز)',
        'platform.inference.unknown_input_per_million_usd' => 'سعر إدخال النموذج غير المعروف (بالدولار لكل مليون رمز)',
        'platform.inference.unknown_output_per_million_usd' => 'سعر إخراج النموذج غير المعروف (بالدولار لكل مليون رمز)',
        // Platform — Frankfurter FX
        'platform.frankfurter.cache_ttl' => 'نافذة حداثة أسعار صرف Frankfurter (بالثواني)',
        'platform.frankfurter.timeout' => 'مهلة طلبات أسعار الصرف إلى Frankfurter (بالثواني)',
        // Archivd
        'archivd.max_file_size_bytes' => 'الحد الأقصى لحجم الملف (بالبايت)',
        'archivd.max_files_per_batch' => 'الحد الأقصى لعدد الملفات في الدفعة',
        'archivd.max_concurrent_uploads' => 'الحد الأقصى لعمليات الرفع المتزامنة',
        'archivd.max_concurrent_parts' => 'الحد الأقصى لأجزاء الرفع متعددة الأجزاء المتزامنة',
        'archivd.chunk_size_bytes' => 'حجم جزء الرفع (بالبايت)',
        'archivd.multipart_threshold_bytes' => 'حد الرفع متعدد الأجزاء (بالبايت)',
        'archivd.max_presigned_parts_per_response' => 'الحد الأقصى للأجزاء الموقعة مسبقًا في الاستجابة',
        'archivd.presign_ttl_minutes' => 'مدة صلاحية عنوان URL الموقّع مسبقًا (بالدقائق)',
        'archivd.session_ttl_hours' => 'مدة صلاحية جلسة الرفع (بالساعات)',
        'archivd.stale_upload_grace_hours' => 'فترة السماح للرفع القديم (بالساعات)',
        'archivd.orphan_inactivity_minutes' => 'مهلة عدم نشاط الجزء اليتيم (بالدقائق)',
        'archivd.default_quota_bytes' => 'حصة التخزين الافتراضية (بالبايت)',
        'archivd.base_owned_workspace_slots' => 'خانات مساحات العمل المملوكة الأساسية',
        'archivd.disabled_regions' => 'مناطق التخزين المعطلة',
        'archivd.signed_download_ttl_minutes' => 'مدة صلاحية عنوان URL للتنزيل الموقّع (بالدقائق)',
        'archivd.signed_download_cache_margin_seconds' => 'هامش ذاكرة التخزين المؤقت للتنزيل الموقّع (بالثواني)',
        'archivd.public_download_signature_ttl_minutes' => 'مدة صلاحية توقيع التنزيل العام (بالدقائق)',
        'archivd.allowed_mime_prefixes' => 'بادئات أنواع MIME المسموح بها',
        'archivd.download_vault.enabled' => 'تفعيل وكيل خزنة التنزيل',
        // Microstock
        'microstock.daily_free.enabled' => 'تفعيل التنزيلات المجانية اليومية',
        'microstock.daily_free.limit_per_user' => 'حد التنزيلات المجانية اليومية لكل مستخدم',
        'microstock.daily_free.timezone' => 'المنطقة الزمنية لإعادة ضبط التنزيل المجاني اليومي',
        'microstock.ai_billing.markup_multiplier' => 'معامل زيادة فوترة الذكاء الاصطناعي',
        'microstock.ai_billing.estimated_billable_micro_usd_per_asset' => 'التكلفة التقديرية القابلة للفوترة بالميكرو-دولار لكل أصل',
        'microstock.ai_billing.estimate_thinking_attempt_factor' => 'معامل تقدير محاولات التفكير',
        'microstock.ai.image_max_px' => 'الحد الأقصى لأبعاد صورة الذكاء الاصطناعي (بكسل)',
        'microstock.ai.image_jpeg_quality' => 'جودة JPEG لصورة الذكاء الاصطناعي',
        'microstock.ai.max_request_image_bytes' => 'الحد الأقصى لحجم صورة الطلب (بالبايت)',
        'microstock.ai.http_retries' => 'عدد إعادة محاولة طلبات الذكاء الاصطناعي',
        'microstock.ai.thinking_visual_attempts' => 'محاولات الرؤية في مرحلة التفكير',
        'microstock.ai.batch_chunk' => 'حجم جزء دفعة الذكاء الاصطناعي',
        'microstock.ai.batch_min_files' => 'الحد الأدنى لملفات دفعة الذكاء الاصطناعي',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'microstock.ai.phase.normal_after' => 'مرحلة الذكاء الاصطناعي: التحول إلى المعالجة العادية بعد (بالثواني)',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'microstock.ai.phase.grace_after' => 'مرحلة الذكاء الاصطناعي: بدء معالجة فترة السماح بعد (بالثواني)',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'microstock.ai.stage.vision_grace_after' => 'مرحلة الذكاء الاصطناعي: دخول الرؤية وضع السماح بعد (بالثواني)',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'microstock.ai.stage.thinking_grace_after' => 'مرحلة الذكاء الاصطناعي: دخول التفكير وضع السماح بعد (بالثواني)',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'microstock.ai.stage.thinking_backlog_after' => 'مرحلة الذكاء الاصطناعي: انتقال مهام التفكير إلى قائمة الانتظار المتراكمة بعد (بالثواني)',
        'microstock.ai.vision_image.delivery' => 'طريقة تسليم صورة الرؤية',
        // curated at 2026-09-22T20:24:46+07:00 — ar curation wave
        'microstock.ai.vision_image.variant_kind' => 'نوع متغيّر صورة الرؤية',
        'microstock.ai.vision_image.signed_url_ttl_minutes' => 'مدة صلاحية عنوان URL الموقّع لصورة الرؤية (بالدقائق)',
        'microstock.ai.inference_provider_slug' => 'معرّف مزود استدلال Microstock',
        'microstock.ai.vision.connection' => 'اتصال الذكاء الاصطناعي للرؤية (فارغ = الافتراضي)',
        'microstock.ai.thinking.connection' => 'اتصال الذكاء الاصطناعي للتفكير (فارغ = الافتراضي)',
        'microstock.ai.vision.model' => 'نموذج الرؤية',
        'microstock.ai.vision.free_model' => 'تجاوز اختياري لنموذج المستوى المجاني للرؤية (فارغ = النموذج المدفوع)',
        'microstock.ai.vision.timeout' => 'مهلة HTTP للرؤية (بالثواني)',
        'microstock.ai.thinking.model' => 'نموذج التفكير',
        'microstock.ai.thinking.free_model' => 'تجاوز اختياري لنموذج المستوى المجاني للتفكير (فارغ = النموذج المدفوع)',
        'microstock.ai.thinking.timeout' => 'مهلة HTTP للتفكير (بالثواني)',
        'microstock.ai.upstream.window_seconds' => 'نافذة ميزانية الرموز للمزود الخارجي (بالثواني)',
        'microstock.ai.upstream.token_budget' => 'ميزانية الرموز للمزود الخارجي لكل نافذة',
        'microstock.ai.upstream.max_concurrent_vision' => 'الحد الأقصى لطلبات الرؤية المتزامنة',
        'microstock.ai.upstream.max_concurrent_thinking' => 'الحد الأقصى لطلبات التفكير المتزامنة',
        'microstock.ai.upstream.collection_vision_concurrency' => 'تزامن الرؤية للمجموعة',
        'microstock.ai.upstream.collection_thinking_concurrency' => 'تزامن التفكير للمجموعة',
        'microstock.ai.upstream.solo_vision_concurrency' => 'تزامن الرؤية الفردية',
        'microstock.ai.upstream.solo_thinking_concurrency' => 'تزامن التفكير الفردي',
        'microstock.ai.upstream.release_after_seconds' => 'تحرير خانة المزود الخارجي بعد (بالثواني)',
        'microstock.csv_disk' => 'قرص تصدير CSV',
    ],
    'hints' => [
        'platform.mail.from_address' => 'عنوان المرسل العام للبريد الصادر (تبقى بيانات اعتماد النقل في .env).',
        'platform.payment.default_buyer_phone' => 'رقم بديل عندما لا يحتوي ملف المشتري على هاتف (متطلب iPaymu).',
        'platform.frankfurter.timeout' => 'مهلة طلبات أسعار الصرف إلى Frankfurter عبر HTTP.',
        'microstock.ai.vision.free_model' => 'تجاوز اختياري لنموذج الرؤية المجاني؛ القيمة الفارغة ترث نموذج الرؤية المدفوع.',
        'microstock.ai.thinking.free_model' => 'تجاوز اختياري لنموذج التفكير المجاني؛ القيمة الفارغة ترث نموذج التفكير المدفوع.',
        'microstock.ai.vision.connection' => 'معرّف اتصال الذكاء الاصطناعي (الإعدادات ← اتصالات الذكاء الاصطناعي) لمرحلة الرؤية؛ فارغ يستخدم الاتصال الافتراضي.',
        'microstock.ai.thinking.connection' => 'معرّف اتصال الذكاء الاصطناعي (الإعدادات ← اتصالات الذكاء الاصطناعي) لمرحلة التفكير؛ فارغ يستخدم الاتصال الافتراضي.',
        'microstock.ai.upstream.window_seconds' => 'نافذة مرجعية لميزانية رموز المزود الخارجي (يُفرض الحد عبر قيود التزامن).',
        'platform.jwt.refresh_skew' => 'حدّث رمز الوصول قبل انتهاء صلاحيته بهذا العدد من الدقائق.',
        // curated at 2026-09-22T20:27:19+07:00 — ar curation wave
        'platform.notifications.delivery' => 'يستخدم الوضع الفعلي WebSockets ويتطلب إعداد بيانات اعتماد PUSHER_* / البث؛ وإلا فتعود أيقونة الإشعارات إلى استطلاع HTTP.',
        // curated at 2026-09-22T20:51:39+07:00 — wave 2 follow-up: enabled label consistency + hint punctuation
        'platform.payment.channel_fee_bearer' => 'الطرف الذي يتحمل رسوم قناة الدفع: التاجر أو العميل.',
        'platform.inference.default_provider_slug' => 'المزود البديل عند عدم تحديد مزود في الطلب.',
        'platform.inference.default_input_per_million_usd' => 'سعر فوترة مؤقت للنماذج المسجلة التي لا تملك سعرًا في الكتالوج.',
        'platform.inference.unknown_input_per_million_usd' => 'سعر فوترة مؤقت للنماذج غير المسجلة.',
        'microstock.ai.phase.normal_after' => 'عدد الثواني قبل انتقال الأصل من المعالجة السريعة إلى المعالجة العادية.',
        'microstock.ai.phase.grace_after' => 'عدد الثواني قبل بدء معالجة فترة السماح.',
        'microstock.ai.stage.vision_grace_after' => 'عدد الثواني قبل دخول مرحلة الرؤية في وضع السماح.',
        'microstock.ai.stage.thinking_grace_after' => 'عدد الثواني قبل دخول مرحلة التفكير في وضع السماح.',
        'microstock.ai.stage.thinking_backlog_after' => 'عدد الثواني قبل نقل مهام التفكير إلى قائمة الانتظار المتراكمة.',
        // curated at 2026-09-27T19:30:00+07:00 — ar curation wave
        'identity.discord.connections_snapshot_max_age_minutes' => 'المدة التي يُعتمد خلالها على لقطات اتصالات Discord المحفوظة قبل أن يؤدي فشل التحقق إلى تحديث مباشر من Discord API.',
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
