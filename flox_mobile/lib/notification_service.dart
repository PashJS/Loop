import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:timezone/data/latest_all.dart' as tz;
import 'package:timezone/timezone.dart' as tz;
import 'package:flutter/services.dart';
import 'package:flutter/foundation.dart';
import 'dart:io';
import 'package:flutter_timezone/flutter_timezone.dart';

class NotificationService {
  static final NotificationService _instance = NotificationService._internal();
  factory NotificationService() => _instance;
  NotificationService._internal();

  final FlutterLocalNotificationsPlugin flutterLocalNotificationsPlugin =
      FlutterLocalNotificationsPlugin();

  bool _initialized = false;

  Future<void> init() async {
    if (_initialized) return;

    try {
      debugPrint('NotificationService: Initializing timezones...');
      tz.initializeTimeZones();

      String? name;
      try {
        final dynamic result = await FlutterTimezone.getLocalTimezone();
        if (result is String) {
          name = result;
        } else if (result != null) {
          try {
            name = (result as dynamic).name?.toString();
          } catch (e) {
            name = result.toString();
          }
        }
      } catch (e) {
        debugPrint('NotificationService: Error getting local timezone: $e');
      }

      name ??= 'UTC';

      try {
        tz.setLocalLocation(tz.getLocation(name));
        debugPrint('NotificationService: Timezone set to $name');
      } catch (e) {
        debugPrint(
          'NotificationService: Location $name not found, falling back to UTC',
        );
        tz.setLocalLocation(tz.getLocation('UTC'));
      }
    } catch (e) {
      debugPrint('NotificationService: Critical timezone setup error: $e');
    }

    try {
      debugPrint('NotificationService: Setting up notification settings...');
      const AndroidInitializationSettings initializationSettingsAndroid =
          AndroidInitializationSettings('@mipmap/ic_launcher');

      const DarwinInitializationSettings initializationSettingsDarwin =
          DarwinInitializationSettings(
            requestAlertPermission: true,
            requestBadgePermission: true,
            requestSoundPermission: true,
          );

      const InitializationSettings initializationSettings =
          InitializationSettings(
            android: initializationSettingsAndroid,
            iOS: initializationSettingsDarwin,
            macOS: initializationSettingsDarwin,
          );

      await flutterLocalNotificationsPlugin.initialize(
        settings: initializationSettings,
        onDidReceiveNotificationResponse: (details) {
          debugPrint('Notification tapped: ${details.payload}');
        },
      );

      if (Platform.isAndroid) {
        final androidPlugin = flutterLocalNotificationsPlugin
            .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin
            >();

        // Create the background channel too
        const AndroidNotificationChannel bgChannel = AndroidNotificationChannel(
          'floxwatch_bg_channel',
          'Background Service',
          description: 'This channel is used for background notifications',
          importance: Importance.low,
        );

        await androidPlugin?.createNotificationChannel(bgChannel);
        await androidPlugin?.requestNotificationsPermission();

        try {
          final bool? canScheduleExact = await androidPlugin
              ?.canScheduleExactNotifications();
          if (canScheduleExact == false) {
            debugPrint(
              'NotificationService: Exact alarms NOT permitted. User action may be required in settings.',
            );
          }
        } catch (e) {
          debugPrint(
            'NotificationService: Error checking exact alarm permission: $e',
          );
        }
      }

      _initialized = true;
      debugPrint('NotificationService: Plugin initialized successfully');
    } catch (e) {
      debugPrint('NotificationService: Critical initialization error: $e');
    }
  }

  Future<void> showInstantNotification({
    required String title,
    required String body,
  }) async {
    try {
      if (!_initialized) await init();

      final AndroidNotificationDetails androidPlatformChannelSpecifics =
          AndroidNotificationDetails(
            'floxwatch_v7_channel',
            'Loop Alerts',
            channelDescription: 'Notification channel for Loop',
            importance: Importance.max,
            priority: Priority.high,
            ticker: 'ticker',
            playSound: true,
            sound: const RawResourceAndroidNotificationSound(
              'notification_sound',
            ),
            enableVibration: true,
          );

      final NotificationDetails platformChannelSpecifics = NotificationDetails(
        android: androidPlatformChannelSpecifics,
        iOS: const DarwinNotificationDetails(
          presentAlert: true,
          presentBadge: true,
          presentSound: true,
        ),
      );

      await flutterLocalNotificationsPlugin.show(
        id: DateTime.now().millisecond,
        title: title,
        body: body,
        notificationDetails: platformChannelSpecifics,
      );

      HapticFeedback.lightImpact();
    } catch (e) {
      debugPrint('NotificationService: Error showing notification: $e');
    }
  }

  Future<void> scheduleNotification({
    required int id,
    required String title,
    required String body,
    required int secondsDelay,
  }) async {
    try {
      if (!_initialized) await init();

      // HYBRID APPROACH:
      // If the delay is very short (<= 10 seconds), avoid the "Exact Alarm" permission complexity
      // and just use a Dart timer. This ensures the user sees the test notification immediately
      // without needing to fiddle with system settings.
      if (secondsDelay <= 10) {
        debugPrint(
          'NotificationService: Short delay detected ($secondsDelay s). Using Timer instead of AlarmManager.',
        );
        Future.delayed(Duration(seconds: secondsDelay), () {
          showInstantNotification(title: title, body: body);
        });
        return;
      }

      // For longer delays, we try the proper scheduling way
      AndroidScheduleMode scheduleMode =
          AndroidScheduleMode.exactAllowWhileIdle;

      if (Platform.isAndroid) {
        final androidPlugin = flutterLocalNotificationsPlugin
            .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin
            >();
        final bool? canScheduleExact = await androidPlugin
            ?.canScheduleExactNotifications();
        if (canScheduleExact == false) {
          debugPrint(
            'NotificationService: Falling back to inexact schedule mode',
          );
          scheduleMode = AndroidScheduleMode.inexactAllowWhileIdle;
        }
      }

      await flutterLocalNotificationsPlugin.zonedSchedule(
        id: id,
        title: title,
        body: body,
        scheduledDate: tz.TZDateTime.now(
          tz.local,
        ).add(Duration(seconds: secondsDelay)),
        notificationDetails: const NotificationDetails(
          android: AndroidNotificationDetails(
            'floxwatch_v7_scheduled',
            'Scheduled Alerts',
            channelDescription: 'Channel for scheduled notifications',
            importance: Importance.max,
            priority: Priority.high,
          ),
          iOS: DarwinNotificationDetails(
            presentAlert: true,
            presentBadge: true,
            presentSound: true,
          ),
        ),
        androidScheduleMode: scheduleMode,

        // This is required to show notification when app is open on iOS
      );
      debugPrint(
        'NotificationService: Notification scheduled successfully with mode: $scheduleMode',
      );
    } catch (e) {
      debugPrint('NotificationService: Error scheduling notification: $e');

      // Secondary fallback
      if (e.toString().contains('exact_alarms_not_permitted')) {
        try {
          await flutterLocalNotificationsPlugin.zonedSchedule(
            id: id,
            title: title,
            body: body,
            scheduledDate: tz.TZDateTime.now(
              tz.local,
            ).add(Duration(seconds: secondsDelay)),
            notificationDetails: const NotificationDetails(
              android: AndroidNotificationDetails(
                'floxwatch_v7_scheduled',
                'Scheduled Alerts',
                importance: Importance.max,
                priority: Priority.high,
              ),
            ),
            androidScheduleMode: AndroidScheduleMode.inexactAllowWhileIdle,
          );
          debugPrint(
            'NotificationService: Scheduled successfully after explicit fallback',
          );
        } catch (e2) {
          debugPrint('NotificationService: Final fallback failed: $e2');
        }
      }
    }
  }
}
