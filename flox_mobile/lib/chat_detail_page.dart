import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'dart:async';
import 'dart:convert';
import 'dart:ui' as ui;
import 'package:flutter/rendering.dart';
import 'package:http/http.dart' as http;
import 'package:web_socket_channel/web_socket_channel.dart';
import 'widgets/native_liquid_glass.dart';
import 'constants.dart';
import 'notification_service.dart';

/// Lightweight widget that ONLY rebuilds when keyboard height changes
/// Uses a separate widget to isolate the keyboard inset dependency
class _KeyboardInsetWidget extends StatelessWidget {
  final Widget child;

  const _KeyboardInsetWidget({required this.child});

  @override
  Widget build(BuildContext context) {
    // Use viewInsetsOf - more efficient than MediaQuery.of(context).viewInsets
    // Only this widget rebuilds during keyboard animation
    final keyboardHeight = MediaQuery.viewInsetsOf(context).bottom;

    return Positioned(
      left: 0,
      right: 0,
      // Input bar handles its own safe area padding
      bottom: keyboardHeight,
      child: child,
    );
  }
}

/// Chat Detail Page - Full screen chat with input bar and message bubbles
/// Optimized for smooth keyboard open/close animations
class ChatDetailPage extends StatefulWidget {
  final Map<String, dynamic>? user;
  final Map<String, dynamic> peer;
  final bool isGroup;

  const ChatDetailPage({
    super.key,
    this.user,
    required this.peer,
    this.isGroup = false,
  });

  @override
  State<ChatDetailPage> createState() => _ChatDetailPageState();
}

class _ChatDetailPageState extends State<ChatDetailPage>
    with TickerProviderStateMixin {
  final String _baseUrl = AppConstants.baseUrl;
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  Map<String, dynamic>? _replyTo;
  final FocusNode _inputFocus = FocusNode();

  List<Map<String, dynamic>> _messages = [];
  bool _isLoading = true;
  bool _isSending = false;
  bool _initialLoadDone = false;
  bool _isOnline = false;
  String? _lastActiveAt;
  bool _hasMore = true;
  bool _isLoadingMore = false;
  WebSocketChannel? _wsChannel;

  // Real-time background capture for refraction
  // Real-time background capture for refraction (Ispated via Notifier for FPS)
  final GlobalKey _backgroundKey = GlobalKey();
  final ValueNotifier<ui.Image?> _backgroundNotifier = ValueNotifier<ui.Image?>(
    null,
  );

  // Reaction profiles cache
  final Map<String, String?> _userPics = {};
  static List<Map<String, dynamic>>? _cachedEmojis;

  // Notification sound
  // Notification state
  static DateTime? _lastNotificationTime;
  static const _notificationDebounce = Duration(seconds: 3);

  @override
  void initState() {
    super.initState();
    _loadMessages();
    _connectWebSocket();
    _checkOnlineStatus();

    // Scroll to bottom when keyboard opens
    _inputFocus.addListener(_onFocusChange);

    // Start background capture loop
    Future.delayed(const Duration(milliseconds: 150), () {
      if (mounted) _captureBackground();
    });
  }

  void _onFocusChange() {
    if (_inputFocus.hasFocus) {
      // Immediately jump to bottom and keep scrolling during keyboard animation
      if (_scrollController.hasClients) {
        _scrollController.jumpTo(0);
      }
      // Also scroll after keyboard starts animating
      Future.delayed(const Duration(milliseconds: 150), _scrollToBottom);
      Future.delayed(const Duration(milliseconds: 300), _scrollToBottom);
    }
  }

  // Removed expensive background capture logic for the bar

  void _captureBackground() async {
    if (!mounted) return;
    try {
      final boundary =
          _backgroundKey.currentContext?.findRenderObject()
              as RenderRepaintBoundary?;
      if (boundary != null) {
        // Capture at the device's native pixel ratio for crisp, high-end refraction
        final dpr = MediaQuery.of(context).devicePixelRatio;
        final image = await boundary.toImage(pixelRatio: dpr);
        if (mounted) {
          final old = _backgroundNotifier.value;
          _backgroundNotifier.value = image;
          old?.dispose();
        }
      }
    } catch (_) {}

    if (mounted) {
      // Re-capture periodically - 16ms for buttery smooth updates
      Future.delayed(const Duration(milliseconds: 16), _captureBackground);
    }
  }

  @override
  void dispose() {
    _backgroundNotifier.value?.dispose();
    _backgroundNotifier.dispose();
    _messageController.dispose();
    _scrollController.dispose();
    _inputFocus.removeListener(_onFocusChange);
    _inputFocus.dispose();
    _wsChannel?.sink.close();
    super.dispose();
  }

  void _checkOnlineStatus() {
    final lastActive = widget.peer['last_active_at'] ?? _lastActiveAt;
    if (lastActive != null) {
      try {
        final lastActiveTime = DateTime.parse(lastActive);
        final diff = DateTime.now().difference(lastActiveTime);
        setState(() {
          _isOnline = diff.inMinutes < 5;
          _lastActiveAt = lastActive;
        });
      } catch (e) {
        setState(() => _isOnline = false);
      }
    }
  }

  String _getOnlineStatus() {
    if (_isOnline) return 'Online';
    if (_lastActiveAt != null) {
      try {
        final lastActive = DateTime.parse(_lastActiveAt!);
        final diff = DateTime.now().difference(lastActive);
        if (diff.inMinutes < 60) return 'Active ${diff.inMinutes}m ago';
        if (diff.inHours < 24) return 'Active ${diff.inHours}h ago';
        if (diff.inDays < 7) return 'Active ${diff.inDays}d ago';
        return 'Offline';
      } catch (e) {
        return 'Offline';
      }
    }
    return widget.isGroup ? 'Group Chat' : 'Offline';
  }

  void _connectWebSocket() {
    try {
      _wsChannel = WebSocketChannel.connect(Uri.parse(AppConstants.wsUrl));
      _wsChannel!.stream.listen(
        (message) => _handleWsMessage(message),
        onDone: () {
          Future.delayed(const Duration(seconds: 3), _connectWebSocket);
        },
        onError: (error) => debugPrint('WebSocket error: $error'),
      );

      final userId = widget.user?['id'];
      if (userId != null) {
        _wsChannel!.sink.add(
          jsonEncode({
            'type': 'JOIN_STREAM',
            'streamId': 'user_$userId',
            'userId': userId,
          }),
        );
      }
    } catch (e) {
      debugPrint('WebSocket connection failed: $e');
    }
  }

  void _handleWsMessage(String message) {
    try {
      final data = jsonDecode(message);
      if (data['type'] == 'NEW_PRIVATE_MESSAGE' ||
          data['type'] == 'GROUP_MESSAGE') {
        final senderId = data['sender_id']?.toString();
        final receiverId = data['receiver_id']?.toString();
        final groupId = data['group_id']?.toString();

        final peerId = widget.peer['id']?.toString();
        final myId = widget.user?['id']?.toString();

        bool isForThisChat = false;
        if (widget.isGroup) {
          isForThisChat = (groupId == peerId);
        } else {
          isForThisChat = (senderId == peerId && receiverId == myId);
        }

        if (isForThisChat && senderId != myId) {
          setState(() {
            _messages.add({
              'id': data['id'] ?? data['message_id'],
              'sender_id': data['sender_id'],
              'message': data['text'] ?? data['message'],
              'created_at':
                  data['timestamp'] ?? DateTime.now().toIso8601String(),
              'is_read': 0,
              'sender_name': data['username'],
              'sender_pic': data['sender_pic'],
            });
          });
          _scrollToBottom();
          _playNotificationSound();

          // Mark as read immediately since we are viewing it
          if (_wsChannel != null) {
            _wsChannel!.sink.add(
              jsonEncode({
                'type': 'MESSAGES_READ',
                'user_id': widget.user?['id'],
                'target_id': peerId,
                'sender_id': widget.user?['id'], // ME reading
                'receiver_id': widget.isGroup ? null : peerId, // Them
                'group_id': widget.isGroup ? peerId : null,
              }),
            );
          }
        }
      } else if (data['type'] == 'MESSAGE_REACTED') {
        final msgId = data['message_id']?.toString();
        final reactions = data['reactions'];
        if (msgId != null) {
          setState(() {
            final index = _messages.indexWhere(
              (m) => m['id'].toString() == msgId,
            );
            if (index != -1) {
              _messages[index]['reactions'] = reactions;
            }
          });
        }
      } else if (data['type'] == 'MESSAGES_READ') {
        final readerId = data['user_id']?.toString();
        // If the person I'm chatting with read the messages
        if (readerId == widget.peer['id']?.toString()) {
          setState(() {
            for (var m in _messages) {
              if (m['sender_id'].toString() == widget.user?['id']?.toString()) {
                m['is_read'] = 1;
                m['is_delivered'] = 1;
              }
            }
          });
        }
      } else if (data['type'] == 'MESSAGE_DELIVERED') {
        final msgId = data['message_id']?.toString();
        if (msgId != null) {
          setState(() {
            final index = _messages.indexWhere(
              (m) => m['id'].toString() == msgId,
            );
            if (index != -1) {
              _messages[index]['is_delivered'] = 1;
            }
          });
        }
      }
    } catch (e) {
      debugPrint('WebSocket message parse error: $e');
    }
  }

  Future<void> _playNotificationSound() async {
    final now = DateTime.now();
    if (_lastNotificationTime != null &&
        now.difference(_lastNotificationTime!) < _notificationDebounce) {
      return;
    }
    _lastNotificationTime = now;
    try {
      NotificationService().showInstantNotification(
        title: widget.peer['username'] ?? 'New Message',
        body: 'You received a new message',
      );
    } catch (e) {
      debugPrint('Error playing notification: $e');
    }
  }

  Future<void> _loadMessages({bool refresh = true}) async {
    if (refresh) {
      setState(() {
        _isLoading = true;
        _hasMore = true;
      });
    } else {
      if (_isLoadingMore || !_hasMore) return;
      setState(() => _isLoadingMore = true);
    }

    try {
      final peerId = widget.peer['id'];
      final userId = widget.user?['id'];

      // Use limit and offset
      // If expanding history (loadMore), offset should be current count
      // Actually backend offset works as "skip N newest messages".
      // So offset = _messages.length.
      final currentOffset = refresh ? 0 : _messages.length;

      final endpoint = widget.isGroup
          ? '$_baseUrl/backend/get_private_messages.php?group_id=$peerId&user_id=$userId&limit=50&offset=$currentOffset'
          : '$_baseUrl/backend/get_private_messages.php?other_id=$peerId&user_id=$userId&limit=50&offset=$currentOffset';

      final res = await http
          .get(Uri.parse(endpoint))
          .timeout(const Duration(seconds: 10));

      final data = jsonDecode(res.body);
      if (data['success'] == true) {
        final messages = List<Map<String, dynamic>>.from(
          data['messages'] ?? [],
        );

        // Parse reactions and cache pics
        Map<String, String?> profilePics = {};
        profilePics[widget.user?['id']?.toString() ?? ''] =
            widget.user?['profile_picture'];
        profilePics[widget.peer['id']?.toString() ?? ''] =
            widget.peer['profile_picture'];

        for (var m in messages) {
          if (m['reactions'] != null && m['reactions'] is String) {
            try {
              m['reactions'] = jsonDecode(m['reactions']);
            } catch (e) {
              m['reactions'] = {};
            }
          }
          if (m['sender_id'] != null && m['sender_pic'] != null) {
            profilePics[m['sender_id'].toString()] = m['sender_pic'];
          }
        }

        if (messages.length < 50) {
          _hasMore = false;
        }

        setState(() {
          if (refresh) {
            _messages = messages;
            _initialLoadDone = true;
            _isLoading = false;
            _scrollToBottom();
          } else {
            // Prepend older messages
            _messages.insertAll(0, messages);
            _isLoadingMore = false;
          }
          _userPics.addAll(profilePics);
          _lastActiveAt = data['last_active_at'];
        });

        // Notify peer that we read the messages
        if (refresh && _messages.isNotEmpty && _wsChannel != null) {
          final peerIdVal = widget.peer['id'];
          _wsChannel!.sink.add(
            jsonEncode({
              'type': 'MESSAGES_READ',
              'user_id': widget.user?['id'],
              'target_id': peerIdVal,
              'sender_id': widget.user?['id'],
              'receiver_id': peerIdVal,
            }),
          );
        }

        if (refresh) _checkOnlineStatus();
      } else {
        setState(() {
          _isLoading = false;
          _isLoadingMore = false;
          // Stop retrying on error to prevent infinite loops
          if (!refresh) _hasMore = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _isLoadingMore = false;
          // Stop retrying on error to prevent infinite loops
          if (!refresh) _hasMore = false;
        });
      }
      debugPrint('Error loading messages: $e');
    }
  }

  Future<void> _reactToMessage(String msgId, String emoji) async {
    HapticFeedback.mediumImpact();
    final myId = widget.user?['id']?.toString();
    if (myId == null) return;

    // Check if user already reacted with a different emoji
    final idx = _messages.indexWhere((m) => m['id'].toString() == msgId);
    if (idx == -1) return;

    final m = _messages[idx];
    var reactions = m['reactions'] ?? {};
    if (reactions is String) reactions = jsonDecode(reactions);
    reactions = Map<String, dynamic>.from(reactions);

    // Find if user already reacted
    String? existingEmoji;
    for (final entry in reactions.entries) {
      final List users = List.from(entry.value);
      if (users.any((u) => u.toString() == myId)) {
        existingEmoji = entry.key;
        break;
      }
    }

    // If clicking same emoji, toggle off. If different emoji, replace.
    setState(() {
      if (existingEmoji == emoji) {
        // Remove my reaction
        final List list = List.from(reactions[emoji]);
        list.removeWhere((u) => u.toString() == myId);
        if (list.isEmpty) {
          reactions.remove(emoji);
        } else {
          reactions[emoji] = list;
        }
      } else {
        // Remove from old emoji if exists
        if (existingEmoji != null) {
          final List oldList = List.from(reactions[existingEmoji]);
          oldList.removeWhere((u) => u.toString() == myId);
          if (oldList.isEmpty) {
            reactions.remove(existingEmoji);
          } else {
            reactions[existingEmoji] = oldList;
          }
        }
        // Add to new emoji
        if (reactions[emoji] == null) {
          reactions[emoji] = [myId];
        } else {
          final List list = List.from(reactions[emoji]);
          if (!list.any((u) => u.toString() == myId)) {
            list.add(myId);
          }
          reactions[emoji] = list;
        }
      }
      m['reactions'] = reactions;
    });

    try {
      final res = await http.post(
        Uri.parse('$_baseUrl/backend/react_to_message.php'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'message_id': msgId, 'reaction': emoji}),
      );

      final data = jsonDecode(res.body);
      if (data['success'] == true) {
        setState(() {
          final idx = _messages.indexWhere((m) => m['id'].toString() == msgId);
          if (idx != -1) {
            _messages[idx]['reactions'] = data['reactions'];
          }
        });

        // Broadcast via WS
        if (_wsChannel != null) {
          _wsChannel!.sink.add(
            jsonEncode({
              'type': 'MESSAGE_REACTED',
              'message_id': msgId,
              'reactions': data['reactions'],
              'receiver_id': data['target_id'],
            }),
          );
        }
      }
    } catch (e) {
      debugPrint('Reaction error: $e');
    }
  }

  ImageProvider _getProfilePicProvider(String? path) {
    if (path == null || path.isEmpty || path == 'null') {
      return const NetworkImage('https://www.gravatar.com/avatar/00?d=mp');
    }
    if (path.startsWith('http')) {
      return NetworkImage(path);
    }
    // Handle local paths
    final clean = path.replaceFirst(RegExp(r'^\.?\/'), '');
    return NetworkImage('$_baseUrl/$clean');
  }

  void _showEmojiPicker(String msgId) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (context) => _EmojiPickerSheet(
        onEmojiSelected: (emoji) {
          Navigator.pop(context);
          _reactToMessage(msgId, emoji);
        },
        backgroundKey: _backgroundKey,
        backgroundNotifier: _backgroundNotifier,
      ),
    );
  }

  Widget _buildReactionsDisplay(String msgId, dynamic reactionsData) {
    if (reactionsData == null) {
      return AnimatedSize(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOutCubic,
        child: const SizedBox.shrink(),
      );
    }

    Map<String, dynamic> reactions;
    if (reactionsData is String) {
      try {
        reactions = jsonDecode(reactionsData);
      } catch (e) {
        return AnimatedSize(
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOutCubic,
          child: const SizedBox.shrink(),
        );
      }
    } else {
      reactions = Map<String, dynamic>.from(reactionsData);
    }

    if (reactions.isEmpty) {
      return AnimatedSize(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOutCubic,
        child: const SizedBox.shrink(),
      );
    }

    return AnimatedSize(
      duration: const Duration(milliseconds: 400),
      curve: Curves.easeOutBack,
      child: Padding(
        padding: const EdgeInsets.only(top: 6),
        child: Wrap(
          spacing: 6,
          runSpacing: 4,
          children: reactions.entries.map((entry) {
            final emoji = entry.key;
            final userIds = List.from(entry.value);
            if (userIds.isEmpty) return const SizedBox.shrink();

            return _buildReactionChip(msgId, emoji, userIds);
          }).toList(),
        ),
      ),
    );
  }

  Widget _buildReactionChip(String msgId, String emoji, List userIds) {
    final bool hasMyReaction = userIds.any(
      (id) => id.toString() == widget.user?['id']?.toString(),
    );

    return _AnimatedReactionChip(
      key: ValueKey('reaction_${msgId}_$emoji'),
      emoji: emoji,
      userIds: userIds,
      hasMyReaction: hasMyReaction,
      onTap: () => _reactToMessage(msgId, emoji),
      avatarBuilder: _buildUserAvatars,
    );
  }

  Widget _buildUserAvatars(List userIds) {
    final displayIds = userIds.take(2).toList();
    return SizedBox(
      width: displayIds.length == 1 ? 18 : 30,
      height: 18,
      child: Stack(
        children: displayIds.asMap().entries.map((entry) {
          final idx = entry.key;
          final userId = entry.value.toString();
          final pic = _userPics[userId];

          return Positioned(
            left: idx * 12.0,
            child: Container(
              width: 18,
              height: 18,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: Colors.black45, width: 1.5),
                image: DecorationImage(
                  image: _getProfilePicProvider(pic),
                  fit: BoxFit.cover,
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  Future<void> _sendMessage() async {
    final text = _messageController.text.trim();
    if (text.isEmpty || _isSending) return;

    setState(() => _isSending = true);
    _messageController.clear();

    final tempId = DateTime.now().millisecondsSinceEpoch;
    final tempMessage = {
      'id': tempId,
      'stable_id': 'temp_$tempId', // Stable key for animation
      'sender_id': widget.user?['id'],
      'message': text,
      'created_at': DateTime.now().toIso8601String(),
      'is_sending': true,
      'has_error': false,
    };
    setState(() => _messages.add(tempMessage));
    HapticFeedback.lightImpact();
    _scrollToBottom();

    final replyPayload = _replyTo != null ? jsonEncode(_replyTo) : null;
    setState(() => _replyTo = null);

    try {
      final res = await http
          .post(
            Uri.parse('$_baseUrl/backend/send_private_message.php'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({
              'sender_id': widget.user?['id'],
              if (widget.isGroup)
                'group_id': widget.peer['id']
              else
                'receiver_id': widget.peer['id'],
              'message': text,
              'reply_to': replyPayload,
            }),
          )
          .timeout(const Duration(seconds: 10));

      final data = jsonDecode(res.body);
      if (data['success'] == true) {
        setState(() {
          final idx = _messages.indexWhere((m) => m['id'] == tempId);
          if (idx != -1) {
            _messages[idx] = {
              ..._messages[idx],
              'id': data['message_id'],
              'is_sending': false,
              'is_read': 0,
              'is_delivered': 1,
              'reply_to': replyPayload,
            };
          }
        });

        if (_wsChannel != null) {
          _wsChannel!.sink.add(
            jsonEncode({
              'type': widget.isGroup ? 'GROUP_MESSAGE' : 'NEW_PRIVATE_MESSAGE',
              'sender_id': widget.user?['id'],
              'username':
                  widget.user?['username'], // Critical for website display
              'sender_pic': widget
                  .user?['profile_picture'], // Critical for website display
              if (widget.isGroup) ...{
                'group_id': widget.peer['id'],
                'target_id': widget.peer['id'],
              } else ...{
                'receiver_id': widget.peer['id'],
                'target_id': widget.peer['id'],
              },
              'text': text,
              'timestamp': DateTime.now().toIso8601String(),
              'id': data['message_id'],
              'reply_to': replyPayload,
            }),
          );
        }
      } else {
        _markMessageError(tempId);
      }
    } catch (e) {
      debugPrint('Error sending message: $e');
      _markMessageError(tempId);
    } finally {
      setState(() => _isSending = false);
    }
  }

  void _markMessageError(int tempId) {
    setState(() {
      final idx = _messages.indexWhere((m) => m['id'] == tempId);
      if (idx != -1) {
        _messages[idx] = {
          ..._messages[idx],
          'is_sending': false,
          'has_error': true,
        };
      }
    });
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        // With reverse: true, position 0 is at the bottom (newest messages)
        _scrollController.animateTo(
          0,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  bool _isSentByMe(Map<String, dynamic> msg) {
    return msg['sender_id']?.toString() == widget.user?['id']?.toString();
  }

  String _formatMessageTime(String? timeStr) {
    if (timeStr == null) return '';
    try {
      final time = DateTime.parse(timeStr);
      final hour = time.hour.toString().padLeft(2, '0');
      final minute = time.minute.toString().padLeft(2, '0');
      return '$hour:$minute';
    } catch (e) {
      return '';
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: const Color(0xFF020205),
        resizeToAvoidBottomInset:
            false, // Handle manually for stable background capture
        body: Stack(
          children: [
            // Stable Background Capture Boundary (Full Screen)
            RepaintBoundary(
              key: _backgroundKey,
              child: Container(
                color: const Color(0xFF020205),
                child: Stack(
                  children: [
                    Positioned.fill(
                      child: _isLoading
                          ? const Center(
                              child: CircularProgressIndicator(
                                color: Color(0xFF007AFF),
                              ),
                            )
                          : _messages.isEmpty
                          ? _buildEmptyState()
                          : _buildMessagesList(),
                    ),
                  ],
                ),
              ),
            ),

            // Header - positioned at top
            Positioned(top: 0, left: 0, right: 0, child: _buildHeader()),

            // Translucent input bar - content shows through
            _KeyboardInsetWidget(child: _buildLiquidGlassInputBar()),
          ],
        ),
      ),
    );
  }

  /// High-Fidelity Liquid Glass Input Bar
  Widget _buildLiquidGlassInputBar() {
    final bottomPadding = MediaQuery.paddingOf(context).bottom;
    final hasReply = _replyTo != null;
    final barHeight = hasReply ? 96.0 : 48.0;

    return Container(
      margin: EdgeInsets.fromLTRB(
        14,
        0,
        14,
        bottomPadding > 0
            ? bottomPadding
            : 34, // Increased lift for floating glass look
      ),
      child: ListenableBuilder(
        listenable: _inputFocus,
        builder: (context, _) {
          return NativeLiquidGlassBar(
            height: barHeight,
            borderRadius: 24,
            backgroundNotifier: _backgroundNotifier,
            child: Column(
              children: [
                AnimatedCrossFade(
                  firstChild: const SizedBox(width: double.infinity, height: 0),
                  secondChild: SizedBox(
                    height: 48,
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 10, 8, 4),
                      child: Row(
                        children: [
                          Container(
                            width: 3,
                            height: double.infinity,
                            decoration: BoxDecoration(
                              color: Colors.blueAccent,
                              borderRadius: BorderRadius.circular(2),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Text(
                                  _replyTo?['sender_name'] ?? 'Reply',
                                  style: const TextStyle(
                                    color: Colors.blueAccent,
                                    fontSize: 12,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(
                                  _replyTo?['message'] ?? '',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    color: Colors.white.withOpacity(0.5),
                                    fontSize: 13,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          IconButton(
                            onPressed: () => setState(() => _replyTo = null),
                            icon: const Icon(
                              Icons.close,
                              color: Colors.white54,
                              size: 20,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  crossFadeState: hasReply
                      ? CrossFadeState.showSecond
                      : CrossFadeState.showFirst,
                  duration: const Duration(milliseconds: 300),
                ),
                SizedBox(
                  height: 48,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      const SizedBox(width: 8),
                      IconButton(
                        padding: EdgeInsets.zero,
                        onPressed: () => _showEmojiPicker(''),
                        icon: const Icon(
                          Icons.sentiment_satisfied_alt_outlined,
                          color: Colors.white54,
                          size: 26,
                        ),
                      ),
                      Expanded(
                        child: ListenableBuilder(
                          listenable: _messageController,
                          builder: (context, _) {
                            return TextField(
                              controller: _messageController,
                              focusNode: _inputFocus,
                              textAlignVertical: TextAlignVertical.center,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 16,
                                fontFamilyFallback: ['AppleEmoji'],
                              ),
                              decoration: const InputDecoration(
                                hintText: 'Message...',
                                hintStyle: TextStyle(
                                  color: Colors.white38,
                                  fontSize: 15,
                                ),
                                border: InputBorder.none,
                                contentPadding: EdgeInsets.only(
                                  bottom: 12,
                                ), // Center vertically
                              ),
                              onSubmitted: (_) => _sendMessage(),
                            );
                          },
                        ),
                      ),
                      ListenableBuilder(
                        listenable: _messageController,
                        builder: (context, _) {
                          final hasText = _messageController.text
                              .trim()
                              .isNotEmpty;
                          return AnimatedSwitcher(
                            duration: const Duration(milliseconds: 200),
                            transitionBuilder: (child, animation) =>
                                ScaleTransition(
                                  scale: animation,
                                  child: FadeTransition(
                                    opacity: animation,
                                    child: child,
                                  ),
                                ),
                            child: Row(
                              key: ValueKey('input_actions_$hasText'),
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                if (!hasText) ...[
                                  IconButton(
                                    onPressed: () {},
                                    icon: const Icon(
                                      Icons.attach_file_rounded,
                                      color: Colors.white54,
                                      size: 24,
                                    ),
                                  ),
                                  IconButton(
                                    onPressed: () {},
                                    icon: const Icon(
                                      Icons.mic_none_rounded,
                                      color: Colors.white54,
                                      size: 24,
                                    ),
                                  ),
                                ] else
                                  IconButton(
                                    onPressed: _sendMessage,
                                    icon: const Icon(
                                      Icons.send_rounded,
                                      color: Color(0xFF007AFF),
                                      size: 24,
                                    ),
                                  ),
                              ],
                            ),
                          );
                        },
                      ),
                      const SizedBox(width: 4),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildHeader() {
    final peerName = widget.peer['username'] ?? widget.peer['name'] ?? 'Chat';
    final peerPic = widget.peer['profile_picture'] ?? widget.peer['picture'];
    final topPadding = MediaQuery.paddingOf(context).top;

    return Padding(
      padding: EdgeInsets.fromLTRB(12, topPadding + 8, 12, 12),
      child: Row(
        children: [
          // Liquid Glass Chevron Button
          ClipRRect(
            borderRadius: BorderRadius.circular(20),
            child: BackdropFilter(
              filter: ui.ImageFilter.blur(sigmaX: 10, sigmaY: 10),
              child: Container(
                width: 40,
                height: 40,
                color: Colors.white.withOpacity(0.1),
                child: Stack(
                  children: [
                    Positioned.fill(
                      child: IgnorePointer(
                        child: CustomPaint(painter: _TopLightningPainter()),
                      ),
                    ),
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      padding: EdgeInsets.zero,
                      icon: const Padding(
                        padding: EdgeInsets.only(left: 6),
                        child: Icon(
                          Icons.arrow_back_ios,
                          color: Colors.white,
                          size: 18,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),

          const SizedBox(width: 12),

          // Liquid Glass Pill with Name + Status (Centered)
          Expanded(
            child: Center(
              child: ClipRRect(
                borderRadius: BorderRadius.circular(20),
                child: BackdropFilter(
                  filter: ui.ImageFilter.blur(sigmaX: 12, sigmaY: 12),
                  child: Stack(
                    children: [
                      // Background + Content
                      Container(
                        height: 40,
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              peerName,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                                height: 1.1,
                              ),
                              textAlign: TextAlign.center,
                              overflow: TextOverflow.ellipsis,
                            ),
                            Text(
                              _getOnlineStatus(),
                              style: TextStyle(
                                color: _isOnline
                                    ? const Color(0xFF22C55E)
                                    : Colors.white.withOpacity(0.5),
                                fontSize: 10,
                                height: 1.1,
                              ),
                              textAlign: TextAlign.center,
                            ),
                          ],
                        ),
                      ),
                      // Lightning border overlay
                      Positioned.fill(
                        child: IgnorePointer(
                          child: CustomPaint(
                            painter: _TopLightningPainter(borderRadius: 20),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),

          const SizedBox(width: 12),

          // Profile Picture Button
          GestureDetector(
            onTap: () {
              ScaffoldMessenger.of(
                context,
              ).showSnackBar(SnackBar(content: Text('$peerName\'s profile')));
            },
            child: Stack(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white.withOpacity(0.1),
                    image: peerPic != null
                        ? DecorationImage(
                            image: NetworkImage('$_baseUrl/$peerPic'),
                            fit: BoxFit.cover,
                          )
                        : null,
                  ),
                  child: peerPic == null
                      ? const Icon(
                          Icons.person,
                          color: Colors.white54,
                          size: 24,
                        )
                      : null,
                ),
                if (_isOnline && !widget.isGroup)
                  Positioned(
                    right: 0,
                    bottom: 0,
                    child: Container(
                      width: 12,
                      height: 12,
                      decoration: BoxDecoration(
                        color: const Color(0xFF22C55E),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: const Color(0xFF0A0A0F),
                          width: 2,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.chat_bubble_outline,
            size: 64,
            color: Colors.white.withOpacity(0.2),
          ),
          const SizedBox(height: 16),
          Text(
            'No messages yet',
            style: TextStyle(
              color: Colors.white.withOpacity(0.5),
              fontSize: 16,
              fontWeight: FontWeight.bold,
              fontFamily: 'SanFranciscoBold',
              fontStyle: FontStyle.normal,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Start the conversation!',
            style: TextStyle(
              color: Colors.white.withOpacity(0.3),
              fontSize: 14,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMessagesList() {
    final keyboardHeight = MediaQuery.viewInsetsOf(context).bottom;
    return ListView.builder(
      key: const PageStorageKey('chat_history'),
      controller: _scrollController,
      reverse: true,
      padding: EdgeInsets.fromLTRB(
        16,
        140, // Top padding for header
        16,
        // Compensate for input bar + keyboard height
        (_replyTo != null ? 112 : 56) +
            keyboardHeight +
            50, // Extra clearance for the floating bar
      ),
      itemCount: _messages.length + (_hasMore ? 1 : 0),

      addAutomaticKeepAlives: true,
      addRepaintBoundaries: true,
      itemBuilder: (context, index) {
        // Handle Load More (Top of list, which is last index in reverse)
        if (index == _messages.length) {
          if (!_isLoadingMore) {
            WidgetsBinding.instance.addPostFrameCallback(
              (_) => _loadMessages(refresh: false),
            );
          }
          return Container(
            padding: const EdgeInsets.all(16),
            alignment: Alignment.center,
            child: const SizedBox(
              width: 24,
              height: 24,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: Colors.white24,
              ),
            ),
          );
        }

        final reversedIndex = _messages.length - 1 - index;
        final msg = _messages[reversedIndex];
        final isSent = _isSentByMe(msg);
        final showTail = _shouldShowTail(reversedIndex);

        // Only animate if it's a truly new message added after initial load
        final creationTime =
            DateTime.tryParse(msg['created_at'] ?? '') ?? DateTime.now();
        final isVeryRecent =
            DateTime.now().difference(creationTime).inSeconds < 1;
        final shouldAnimate =
            _initialLoadDone && (msg['is_sending'] == true || isVeryRecent);

        return _MessageAnimation(
          key: ValueKey('msg_anim_${msg['stable_id'] ?? msg['id']}'),
          isSent: isSent,
          animate: shouldAnimate,
          child: _buildMessageBubble(msg, isSent, showTail, reversedIndex),
        );
      },
    );
  }

  bool _shouldShowTail(int index) {
    if (index == _messages.length - 1) return true;
    final current = _messages[index];
    final next = _messages[index + 1];
    return _isSentByMe(current) != _isSentByMe(next);
  }

  bool _isFirstInGroup(int index) {
    if (index == 0) return true;
    final current = _messages[index];
    final prev = _messages[index - 1];
    return _isSentByMe(current) != _isSentByMe(prev);
  }

  Widget _buildMessageBubble(
    Map<String, dynamic> msg,
    bool isSent,
    bool showTail,
    int index,
  ) {
    final text = msg['message'] ?? '';
    final time = _formatMessageTime(msg['created_at']);
    final isSending = msg['is_sending'] == true;
    final hasError = msg['has_error'] == true;
    final isRead = msg['is_read'] == 1;
    final isDelivered = msg['is_delivered'] == 1 || isRead;
    final isFirst = _isFirstInGroup(index);

    // Matching chat.php colors
    final bubbleColor = isSent
        ? const Color(0xFF007AFF)
        : const Color(0xFF26262A);

    // Delivery Status Widget
    Widget buildStatus() {
      if (!isSent) return const SizedBox.shrink();
      if (hasError) {
        return const Icon(
          Icons.access_time_filled,
          size: 12,
          color: Colors.red,
        );
      }
      if (isSending) {
        return Icon(Icons.done, size: 12, color: Colors.white.withOpacity(0.5));
      }
      if (isRead) {
        return const Icon(Icons.done_all, size: 12, color: Color(0xFF40C4FF));
      }
      if (isDelivered) {
        return Icon(
          Icons.done_all,
          size: 12,
          color: Colors.white.withOpacity(0.5),
        );
      }
      return Icon(Icons.done, size: 12, color: Colors.white.withOpacity(0.5));
    }

    return _SwipeToReplyWrapper(
      key: ValueKey('msg_${msg['id']}'),
      onReply: () {
        setState(() {
          _replyTo = {
            'id': msg['id'],
            'message': text,
            'sender_id': msg['sender_id'],
            'sender_name': isSent
                ? (widget.user?['username'] ?? 'Me')
                : (widget.peer['username'] ?? 'User'),
          };
        });
        _inputFocus.requestFocus();
      },
      child: GestureDetector(
        onTap: () => _showEmojiPicker(msg['id'].toString()),
        onDoubleTap: () => _reactToMessage(msg['id'].toString(), '❤️'),
        child: Padding(
          padding: EdgeInsets.only(
            bottom: showTail ? 10 : 2,
            left: isSent ? 40 : 0,
            right: isSent ? 0 : 40,
          ),
          child: Align(
            alignment: isSent ? Alignment.centerRight : Alignment.centerLeft,
            child: Stack(
              clipBehavior: Clip.none,
              children: [
                // Main bubble
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: bubbleColor,
                    borderRadius: BorderRadius.only(
                      topLeft: Radius.circular(!isSent && !isFirst ? 4 : 20),
                      topRight: Radius.circular(isSent && !isFirst ? 4 : 20),
                      bottomLeft: Radius.circular(isSent ? 20 : 4),
                      bottomRight: Radius.circular(isSent ? 4 : 20),
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.1),
                        blurRadius: 4,
                        offset: const Offset(0, 1),
                      ),
                    ],
                  ),
                  child: _buildBubbleContent(
                    msg['id'].toString(),
                    text,
                    time,
                    buildStatus(),
                    isSent,
                    msg['reply_to'],
                    msg['reactions'],
                  ),
                ),
                // Tail at bottom corner - Replica of chat.php SVG tail
                if (showTail)
                  Positioned(
                    bottom: -1,
                    right: isSent ? -7 : null,
                    left: !isSent ? -7 : null,
                    child: CustomPaint(
                      size: const Size(34, 28),
                      painter: _ChatPhpTailPainter(
                        color: bubbleColor,
                        isSent: isSent,
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildBubbleContent(
    String msgId,
    String text,
    String time,
    Widget status,
    bool isSent,
    String? replyToRaw,
    dynamic reactions,
  ) {
    Map<String, dynamic>? replyTo;
    if (replyToRaw != null) {
      try {
        replyTo = jsonDecode(replyToRaw);
      } catch (e) {
        // ignore
      }
    }

    const textStyle = TextStyle(
      color: Colors.white,
      fontSize: 16,
      height: 1.2,
      fontFamilyFallback: ['AppleEmoji'],
    );

    // Heuristic for single line: less than 40 chars and no newlines and NO REPLY
    final bool isShort =
        text.length < 40 && !text.contains('\n') && replyTo == null;

    return Column(
      crossAxisAlignment: isSent
          ? CrossAxisAlignment.end
          : CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (replyTo != null) ...[
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            margin: const EdgeInsets.only(bottom: 6),
            decoration: BoxDecoration(
              color: Colors.black.withOpacity(0.2),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 2,
                  height: 24,
                  color: isSent ? Colors.white70 : Colors.blueAccent,
                ),
                const SizedBox(width: 8),
                Flexible(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        replyTo['sender_name'] ?? 'Reply',
                        style: TextStyle(
                          color: isSent ? Colors.white70 : Colors.blueAccent,
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      Text(
                        replyTo['message'] ?? '',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: Colors.white.withOpacity(0.5),
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
        if (isShort)
          Row(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Flexible(child: Text(text, style: textStyle)),
              const SizedBox(width: 8),
              Padding(
                padding: const EdgeInsets.only(bottom: 2),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      time,
                      style: TextStyle(
                        color: Colors.white.withOpacity(0.6),
                        fontSize: 11,
                      ),
                    ),
                    if (isSent) ...[const SizedBox(width: 4), status],
                  ],
                ),
              ),
            ],
          )
        else
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(text, style: textStyle),
              const SizedBox(height: 4),
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    time,
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.6),
                      fontSize: 11,
                    ),
                  ),
                  if (isSent) ...[const SizedBox(width: 4), status],
                ],
              ),
            ],
          ),
        _buildReactionsDisplay(msgId, reactions),
      ],
    );
  }
}

/// Premium Entry Animation for Chat Messages
class _MessageAnimation extends StatefulWidget {
  final Widget child;
  final bool isSent;

  final bool animate;

  const _MessageAnimation({
    super.key,
    required this.child,
    required this.isSent,
    this.animate = true,
  });

  @override
  State<_MessageAnimation> createState() => _MessageAnimationState();
}

class _MessageAnimationState extends State<_MessageAnimation>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<Offset> _slide;
  late Animation<double> _opacity;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 600), // Smooth slower travel
    );

    _opacity = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _controller,
        curve: const Interval(0.0, 0.4, curve: Curves.easeIn),
      ),
    );

    _slide =
        Tween<Offset>(
          begin: const Offset(0.0, 0.5), // Emerges from input bar area
          end: Offset.zero,
        ).animate(
          CurvedAnimation(
            parent: _controller,
            curve: const Interval(0.1, 1.0, curve: Curves.easeOutCubic),
          ),
        );

    if (widget.animate) {
      _controller.forward();
    } else {
      _controller.value = 1.0;
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!widget.animate) return widget.child;

    return FadeTransition(
      opacity: _opacity,
      child: SlideTransition(position: _slide, child: widget.child),
    );
  }
}

/// Exact replica of the tail from chat.php
class _ChatPhpTailPainter extends CustomPainter {
  final Color color;
  final bool isSent;

  _ChatPhpTailPainter({required this.color, required this.isSent});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.fill;

    if (isSent) {
      canvas.translate(size.width, 0);
      canvas.scale(-1, 1);
    }

    final double scaleX = size.width / 28.0;
    final double scaleY = size.height / 23.0;
    canvas.scale(scaleX, scaleY);

    final path = Path();
    path.moveTo(2.50308, 22.6242);
    path.cubicTo(0.391605, 22.5736, 0.0, 21.2128, 0.0, 21.2128);
    path.cubicTo(0.0, 21.2128, 0.613831, 21.8576, 1.13275, 22.0118);
    path.cubicTo(2.06803, 22.2897, 2.44507, 22.0118, 3.49995, 21.2128);
    path.cubicTo(4.55482, 20.4138, 6.31004, 15.4996, 6.00004, 14.2132);
    path.cubicTo(8.73292, 13.2285, 12.1798, 15.8136, 11.9999, 18.7128);
    path.cubicTo(11.3051, 21.1679, 6.90224, 22.7297, 2.50308, 22.6242);
    path.close();

    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant _ChatPhpTailPainter oldDelegate) {
    return oldDelegate.color != color || oldDelegate.isSent != isSent;
  }
}

class _EmojiPickerSheet extends StatefulWidget {
  final Function(String) onEmojiSelected;
  final GlobalKey backgroundKey;
  final ValueNotifier<ui.Image?> backgroundNotifier;

  const _EmojiPickerSheet({
    required this.onEmojiSelected,
    required this.backgroundKey,
    required this.backgroundNotifier,
  });

  @override
  State<_EmojiPickerSheet> createState() => _EmojiPickerSheetState();
}

class _EmojiPickerSheetState extends State<_EmojiPickerSheet> {
  final TextEditingController _searchController = TextEditingController();
  final ValueNotifier<List<Map<String, dynamic>>> _filteredList = ValueNotifier(
    [],
  );
  List<Map<String, dynamic>> _allEmojis = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadEmojis();
  }

  @override
  void dispose() {
    _searchController.dispose();
    _filteredList.dispose();
    super.dispose();
  }

  Future<void> _loadEmojis() async {
    if (_ChatDetailPageState._cachedEmojis != null) {
      if (mounted) {
        _allEmojis = _ChatDetailPageState._cachedEmojis!;
        _filteredList.value = _allEmojis;
        setState(() => _isLoading = false);
      }
      return;
    }

    try {
      final res = await http.get(
        Uri.parse(
          'https://emoji-api.com/emojis?access_key=a0144fbf691a1baa5592e15f709fd9c5b829b194',
        ),
      );
      final data = jsonDecode(res.body) as List;
      final emojis = data
          .map((e) => {'char': e['character'], 'name': e['slug']})
          .toList()
          .cast<Map<String, dynamic>>();

      _ChatDetailPageState._cachedEmojis = emojis;

      if (mounted) {
        _allEmojis = emojis;
        _filteredList.value = emojis;
        setState(() => _isLoading = false);
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _filterEmojis(String query) {
    if (query.isEmpty) {
      _filteredList.value = _allEmojis;
    } else {
      final q = query.toLowerCase();
      _filteredList.value = _allEmojis
          .where((e) => e['name'].toString().toLowerCase().contains(q))
          .toList();
    }
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.6,
      minChildSize: 0.4,
      maxChildSize: 0.9,
      expand: false,
      builder: (context, scrollController) {
        return Container(
          decoration: BoxDecoration(
            color: Colors.black.withOpacity(0.8),
            borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Stack(
            children: [
              // Glassmorphic background
              Positioned.fill(
                child: ClipRRect(
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(24),
                  ),
                  child: BackdropFilter(
                    filter: ui.ImageFilter.blur(sigmaX: 30, sigmaY: 30),
                    child: Container(color: Colors.white.withOpacity(0.05)),
                  ),
                ),
              ),
              Column(
                children: [
                  const SizedBox(height: 12),
                  Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: Colors.white24,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: NativeLiquidGlassBar(
                      height: 50,
                      borderRadius: 25,
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Row(
                          children: [
                            const Icon(
                              Icons.search,
                              color: Colors.white38,
                              size: 20,
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextField(
                                controller: _searchController,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 15,
                                ),
                                decoration: const InputDecoration(
                                  hintText: 'Search reactions...',
                                  hintStyle: TextStyle(color: Colors.white24),
                                  border: InputBorder.none,
                                ),
                                onChanged: _filterEmojis,
                              ),
                            ),
                            ListenableBuilder(
                              listenable: _searchController,
                              builder: (context, _) {
                                if (_searchController.text.isEmpty) {
                                  return const SizedBox.shrink();
                                }
                                return IconButton(
                                  icon: const Icon(
                                    Icons.close,
                                    color: Colors.white38,
                                    size: 18,
                                  ),
                                  onPressed: () {
                                    _searchController.clear();
                                    _filterEmojis('');
                                  },
                                );
                              },
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  Expanded(
                    child: _isLoading
                        ? const Center(
                            child: CircularProgressIndicator(
                              color: Colors.blueAccent,
                            ),
                          )
                        : ValueListenableBuilder<List<Map<String, dynamic>>>(
                            valueListenable: _filteredList,
                            builder: (context, filtered, _) {
                              return RepaintBoundary(
                                child: GridView.builder(
                                  controller: scrollController,
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 16,
                                    vertical: 8,
                                  ),
                                  gridDelegate:
                                      const SliverGridDelegateWithFixedCrossAxisCount(
                                        crossAxisCount: 7,
                                        mainAxisSpacing: 12,
                                        crossAxisSpacing: 12,
                                      ),
                                  itemCount: filtered.length,
                                  cacheExtent: 1000,
                                  itemBuilder: (context, index) {
                                    final emoji = filtered[index]['char'];
                                    return InkResponse(
                                      onTap: () =>
                                          widget.onEmojiSelected(emoji),
                                      radius: 24,
                                      child: Center(
                                        child: Text(
                                          emoji,
                                          style: const TextStyle(fontSize: 26),
                                        ),
                                      ),
                                    );
                                  },
                                ),
                              );
                            },
                          ),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }
}

/// A premium swipe-to-reply wrapper with strict limits and haptic feedback
class _SwipeToReplyWrapper extends StatefulWidget {
  final Widget child;
  final VoidCallback onReply;

  const _SwipeToReplyWrapper({
    super.key,
    required this.child,
    required this.onReply,
  });

  @override
  State<_SwipeToReplyWrapper> createState() => _SwipeToReplyWrapperState();
}

class _SwipeToReplyWrapperState extends State<_SwipeToReplyWrapper> {
  double _dragOffset = 0.0;
  bool _isReplyTriggered = false;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onHorizontalDragUpdate: (details) {
        // Only allow rightward swipe
        if (details.delta.dx > 0 || _dragOffset > 0) {
          setState(() {
            // Apply slight resistance and strict clamp
            _dragOffset = (_dragOffset + details.delta.dx).clamp(0.0, 80.0);

            if (_dragOffset >= 60 && !_isReplyTriggered) {
              _isReplyTriggered = true;
              HapticFeedback.lightImpact();
            } else if (_dragOffset < 50) {
              _isReplyTriggered = false;
            }
          });
        }
      },
      onHorizontalDragEnd: (details) {
        if (_isReplyTriggered) {
          widget.onReply();
        }
        setState(() {
          _dragOffset = 0;
          _isReplyTriggered = false;
        });
      },
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned.fill(
            child: Container(
              alignment: Alignment.centerLeft,
              padding: const EdgeInsets.only(left: 12),
              child: Transform.scale(
                scale: (_dragOffset / 60).clamp(0.0, 1.2),
                child: Icon(
                  Icons.reply_rounded,
                  color: _isReplyTriggered ? Colors.blueAccent : Colors.white30,
                  size: 24,
                ),
              ),
            ),
          ),
          AnimatedContainer(
            duration: Duration(milliseconds: _dragOffset == 0 ? 300 : 0),
            curve: Curves.easeOutBack,
            transform: Matrix4.translationValues(_dragOffset, 0, 0),
            child: widget.child,
          ),
        ],
      ),
    );
  }
}

/// Animated reaction chip with smooth spring animation
class _AnimatedReactionChip extends StatefulWidget {
  final String emoji;
  final List userIds;
  final bool hasMyReaction;
  final VoidCallback onTap;
  final Widget Function(List) avatarBuilder;

  const _AnimatedReactionChip({
    super.key,
    required this.emoji,
    required this.userIds,
    required this.hasMyReaction,
    required this.onTap,
    required this.avatarBuilder,
  });

  @override
  State<_AnimatedReactionChip> createState() => _AnimatedReactionChipState();
}

class _AnimatedReactionChipState extends State<_AnimatedReactionChip>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _scaleAnimation;
  bool _wasMyReaction = false;

  @override
  void initState() {
    super.initState();
    _wasMyReaction = widget.hasMyReaction;
    _controller = AnimationController(
      duration: const Duration(milliseconds: 600),
      vsync: this,
    );
    _scaleAnimation = CurvedAnimation(
      parent: _controller,
      curve: Curves.elasticOut,
    );
    // Start from small and animate to full size
    _controller.forward();
  }

  @override
  void didUpdateWidget(_AnimatedReactionChip oldWidget) {
    super.didUpdateWidget(oldWidget);
    // Pulse animation when my reaction status changes
    if (widget.hasMyReaction != _wasMyReaction) {
      _wasMyReaction = widget.hasMyReaction;
      // Reset and play pulse animation
      _controller.reset();
      _controller.forward();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ScaleTransition(
      scale: _scaleAnimation,
      child: GestureDetector(
        onTap: () {
          // Trigger pulse on tap
          _controller.reset();
          _controller.forward();
          widget.onTap();
        },
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 400),
          curve: Curves.easeOutBack,
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(
            color: widget.hasMyReaction
                ? const Color(0xFF007AFF).withOpacity(0.45)
                : Colors.white.withOpacity(0.08),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: widget.hasMyReaction
                  ? const Color(0xFF007AFF)
                  : Colors.white.withOpacity(0.15),
              width: widget.hasMyReaction ? 2.0 : 1.0,
            ),
            boxShadow: widget.hasMyReaction
                ? [
                    BoxShadow(
                      color: const Color(0xFF007AFF).withOpacity(0.5),
                      blurRadius: 12,
                      spreadRadius: 2,
                    ),
                  ]
                : null,
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              AnimatedScale(
                scale: widget.hasMyReaction ? 1.2 : 1.0,
                duration: const Duration(milliseconds: 300),
                curve: Curves.elasticOut,
                child: Text(widget.emoji, style: const TextStyle(fontSize: 14)),
              ),
              const SizedBox(width: 6),
              widget.avatarBuilder(widget.userIds),
              if (widget.userIds.length > 2) ...[
                const SizedBox(width: 4),
                AnimatedDefaultTextStyle(
                  duration: const Duration(milliseconds: 300),
                  style: TextStyle(
                    color: widget.hasMyReaction ? Colors.white : Colors.white70,
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                  child: Text(widget.userIds.length.toString()),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _TopLightningPainter extends CustomPainter {
  final double borderRadius;

  _TopLightningPainter({this.borderRadius = 20});

  @override
  void paint(Canvas canvas, Size size) {
    if (size.width == 0 || size.height == 0) return;
    final rect = Offset.zero & size;
    // Use provided borderRadius, capped at half-height for pill shapes
    final radius = borderRadius.clamp(0.0, size.height / 2).toDouble();
    final rrect = RRect.fromRectAndRadius(rect, Radius.circular(radius));

    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.0
      ..strokeCap = StrokeCap.round
      ..shader = ui.Gradient.linear(
        const Offset(0, 0),
        Offset(size.width, 0),
        [
          Colors.white.withOpacity(0.0),
          Colors.white.withOpacity(0.6),
          Colors.white.withOpacity(0.6),
          Colors.white.withOpacity(0.0),
        ],
        [0.0, 0.2, 0.8, 1.0],
      );

    canvas.save();
    canvas.clipRect(Rect.fromLTWH(0, 0, size.width, size.height / 2));
    canvas.drawRRect(rrect, paint);
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _TopLightningPainter oldDelegate) =>
      oldDelegate.borderRadius != borderRadius;
}
