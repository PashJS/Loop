import 'dart:async';
import 'dart:convert';
import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:flutter_background_service/flutter_background_service.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:web_socket_channel/io.dart';
import 'package:web_socket_channel/web_socket_channel.dart';
import 'constants.dart';

// Entry point for the background service
@pragma('vm:entry-point')
void onStart(ServiceInstance service) async {
  WidgetsFlutterBinding.ensureInitialized();
  DartPluginRegistrant.ensureInitialized();

  // Initialize Notification Service for local notifications
  // Using a simpler direct approach for background context
  final FlutterLocalNotificationsPlugin flutterLocalNotificationsPlugin =
      FlutterLocalNotificationsPlugin();

  const AndroidInitializationSettings initializationSettingsAndroid =
      AndroidInitializationSettings('@mipmap/ic_launcher');
  final InitializationSettings initializationSettings = InitializationSettings(
    android: initializationSettingsAndroid,
  );
  await flutterLocalNotificationsPlugin.initialize(
    settings: initializationSettings,
  );

  const AndroidNotificationChannel channel = AndroidNotificationChannel(
    'floxwatch_bg_channel', // id
    'Background Service', // title
    description: 'This channel is used for background notifications',
    importance: Importance.low, // vital to not disturb user constantly
  );

  await flutterLocalNotificationsPlugin
      .resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin
      >()
      ?.createNotificationChannel(channel);

  // No need to configure here, it's done in initializeBackgroundService

  service.on('stopService').listen((event) {
    service.stopSelf();
  });

  // Start the WebSocket Connection
  try {
    final manager = _BackgroundWebSocketManager(
      flutterLocalNotificationsPlugin,
    );
    await manager.start();
  } catch (e) {
    debugPrint("[BG] Initialization Error: $e");
  }
}

class _BackgroundWebSocketManager {
  WebSocketChannel? _channel;
  String? _userId;
  Timer? _reconnectTimer;
  final FlutterLocalNotificationsPlugin _notificationsPlugin;

  _BackgroundWebSocketManager(this._notificationsPlugin);

  Future<void> start() async {
    // 1. Get User ID from shared prefs
    final prefs = await SharedPreferences.getInstance();
    final userJson = prefs.getString('auth_user');
    if (userJson != null) {
      try {
        final user = jsonDecode(userJson);
        _userId = user['id']?.toString();
      } catch (_) {}
    }

    if (_userId == null) {
      debugPrint('[BG] No user logged in. Stopping service.');
      return;
    }

    _connect();
  }

  void _connect() {
    if (_userId == null) return;

    try {
      debugPrint('[BG] Connecting to ${AppConstants.wsUrl} for User $_userId');
      _channel = IOWebSocketChannel.connect(Uri.parse(AppConstants.wsUrl));

      // Send Join Command
      _channel!.sink.add(
        jsonEncode({
          'type': 'JOIN_STREAM',
          'streamId': 'user_$_userId',
          'userId': _userId,
        }),
      );

      // Listen
      _channel!.stream.listen(
        _onMessage,
        onDone: _onDisconnect,
        onError: (e) {
          debugPrint('[BG] WS Error: $e');
          _onDisconnect();
        },
      );
    } catch (e) {
      debugPrint('[BG] Connection failed: $e');
      _scheduleReconnect();
    }
  }

  void _onMessage(dynamic message) {
    try {
      final data = jsonDecode(message);

      if (data['type'] == 'NEW_PRIVATE_MESSAGE') {
        final senderName = data['username'] ?? 'Someone';
        final text = data['text'] ?? data['message'] ?? 'Sent a message';

        _showNotification(title: senderName, body: text);
      }
    } catch (e) {
      debugPrint('[BG] Message parse error: $e');
    }
  }

  void _onDisconnect() {
    debugPrint('[BG] Disconnected.');
    _channel?.sink.close();
    _scheduleReconnect();
  }

  void _scheduleReconnect() {
    _reconnectTimer?.cancel();
    _reconnectTimer = Timer(const Duration(seconds: 5), _connect);
  }

  Future<void> _showNotification({
    required String title,
    required String body,
  }) async {
    // We use a high importance channel for actual messages
    const AndroidNotificationDetails androidPlatformChannelSpecifics =
        AndroidNotificationDetails(
          'floxwatch_v7_channel', // Must match the main app's channel
          'Loop Alerts',
          importance: Importance.max,
          priority: Priority.high,
          playSound: true,
        );

    final NotificationDetails platformChannelSpecifics = NotificationDetails(
      android: androidPlatformChannelSpecifics,
    );

    // Correcting the arguments for the show method
    await _notificationsPlugin.show(
      id: DateTime.now().millisecond,
      title: title,
      body: body,
      notificationDetails: platformChannelSpecifics,
    );
  }
}

Future<void> initializeBackgroundService() async {
  final service = FlutterBackgroundService();

  // Initialize the plugin instance for the main isolate
  final FlutterLocalNotificationsPlugin flutterLocalNotificationsPlugin =
      FlutterLocalNotificationsPlugin();

  const AndroidInitializationSettings initializationSettingsAndroid =
      AndroidInitializationSettings('@mipmap/ic_launcher');
  const InitializationSettings initializationSettings = InitializationSettings(
    android: initializationSettingsAndroid,
  );

  await flutterLocalNotificationsPlugin.initialize(
    settings: initializationSettings,
  );

  // Android-specific configuration
  final AndroidConfiguration androidConfiguration = AndroidConfiguration(
    // this will be executed when app is in foreground or background in separated isolate
    onStart: onStart,

    // auto start service
    autoStart: true,
    isForegroundMode: true,

    notificationChannelId: 'floxwatch_bg_channel',
    initialNotificationTitle: 'FloxWatch Service',
    initialNotificationContent: 'Initializing...',
    foregroundServiceNotificationId: 888,
  );

  // iOS-specific configuration
  final IosConfiguration iosConfiguration = IosConfiguration(
    autoStart: true,
    onForeground: onStart,
    onBackground: onStartBackground,
  );

  await service.configure(
    androidConfiguration: androidConfiguration,
    iosConfiguration: iosConfiguration,
  );

  service.startService();
}

@pragma('vm:entry-point')
Future<bool> onStartBackground(ServiceInstance service) async {
  WidgetsFlutterBinding.ensureInitialized();
  DartPluginRegistrant.ensureInitialized();
  return true;
}
