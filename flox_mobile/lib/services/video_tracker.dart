import 'dart:async';
import 'package:flutter/widgets.dart';
import 'package:flutter/foundation.dart';

/// Tracks precise user interactions for the recommendation engine.
/// Designed to be attached to a PageView or similar scrolling list.
class VideoInteractionTracker {
  static final VideoInteractionTracker _instance =
      VideoInteractionTracker._internal();

  factory VideoInteractionTracker() {
    return _instance;
  }

  VideoInteractionTracker._internal() {
    // Start periodic flush timer (every 10 seconds)
    _flushTimer = Timer.periodic(
      const Duration(seconds: 10),
      (_) => flushEvents(),
    );
  }

  // State
  VideoSession? _currentSession;
  final List<InteractionEvent> _eventBuffer = [];
  Timer? _flushTimer;

  // Configuration
  static const int kBatchSize = 10;
  static const int kMinWatchTimeMs = 100; // Filter out accidental 0ms entries

  /// Call when a video becomes visible (e.g., onPageChanged)
  void onVideoStarted(String videoId, {double scrollVelocity = 0.0}) {
    // 1. Close previous session
    if (_currentSession != null) {
      _endCurrentSession();
    }

    // 2. Start new session
    _currentSession = VideoSession(
      videoId: videoId,
      startTime: DateTime.now(),
      scrollEntryVelocity: scrollVelocity,
    );

    debugPrint("Tracker: Started video $videoId");
  }

  /// Call when the user scrolls away or leaves the page
  void onVideoEnded() {
    _endCurrentSession();
  }

  /// Call on user actions
  void recordLike() => _currentSession?.isLiked = true;
  void recordShare() => _currentSession?.isShared = true;
  void recordComment() => _currentSession?.isCommented = true;
  void recordProfileClick() => _currentSession?.isProfileClicked = true;

  /// Internal: Finalize the session and add to buffer
  void _endCurrentSession() {
    final session = _currentSession;
    if (session == null) return;

    final endTime = DateTime.now();
    final duration = endTime.difference(session.startTime).inMilliseconds;

    if (duration >= kMinWatchTimeMs) {
      final event = InteractionEvent(
        videoId: session.videoId,
        watchTimeMs: duration,
        timestamp: endTime,
        isLiked: session.isLiked,
        isShared: session.isShared,
        isCommented: session.isCommented,
        isProfileClicked: session.isProfileClicked,
        entryVelocity: session.scrollEntryVelocity,
        // Calculate rewatch if needed (client-side logic or server-side)
        isRewatch: false,
      );

      _eventBuffer.add(event);
      if (_eventBuffer.length >= kBatchSize) {
        flushEvents();
      }
    }

    _currentSession = null;
  }

  /// Send buffered events to the backend
  Future<void> flushEvents() async {
    if (_eventBuffer.isEmpty) return;

    final batch = List<InteractionEvent>.from(_eventBuffer);
    _eventBuffer.clear();

    try {
      debugPrint("Tracker: Flushing ${batch.length} events to API...");
      // TODO: Replace with your actual API call
      // await ApiService.post('/interactions/batch', body: {'events': batch.map((e) => e.toMap()).toList()});
    } catch (e) {
      debugPrint("Tracker: Failed to flush events. Re-queueing. Error: $e");
      // Put back at the front of the queue to preserve order roughly
      _eventBuffer.insertAll(0, batch);
    }
  }

  void dispose() {
    _flushTimer?.cancel();
    flushEvents(); // Final flush
  }
}

class VideoSession {
  final String videoId;
  final DateTime startTime;
  double scrollEntryVelocity;

  bool isLiked = false;
  bool isShared = false;
  bool isCommented = false;
  bool isProfileClicked = false;

  VideoSession({
    required this.videoId,
    required this.startTime,
    this.scrollEntryVelocity = 0.0,
  });
}

class InteractionEvent {
  final String videoId;
  final int watchTimeMs;
  final DateTime timestamp;
  final bool isLiked;
  final bool isShared;
  final bool isCommented;
  final bool isProfileClicked;
  final double entryVelocity;
  final bool isRewatch;

  InteractionEvent({
    required this.videoId,
    required this.watchTimeMs,
    required this.timestamp,
    required this.isLiked,
    required this.isShared,
    required this.isCommented,
    required this.isProfileClicked,
    required this.entryVelocity,
    required this.isRewatch,
  });

  Map<String, dynamic> toMap() {
    return {
      'video_id': videoId,
      'watch_time_ms': watchTimeMs,
      'timestamp': timestamp.toIso8601String(),
      'is_liked': isLiked,
      'is_shared': isShared,
      'is_commented': isCommented,
      'is_profile_clicked': isProfileClicked,
      'scroll_velocity': entryVelocity,
    };
  }
}
