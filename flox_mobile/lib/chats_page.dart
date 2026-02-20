import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'dart:async';
import 'dart:math' as math;
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:web_socket_channel/web_socket_channel.dart';
import 'stars.dart';
import 'chat_detail_page.dart';
import 'find_people_page.dart';
import 'constants.dart';

class ChatsPage extends StatefulWidget {
  final Map<String, dynamic>? user;
  const ChatsPage({super.key, this.user});

  @override
  State<ChatsPage> createState() => _ChatsPageState();
}

class _ChatsPageState extends State<ChatsPage> with TickerProviderStateMixin {
  late AnimationController _bgController;
  late List<Star> _stars;

  List<Map<String, dynamic>> _conversations = [];
  List<Map<String, dynamic>> _requests = [];
  List<Map<String, dynamic>> _groups = [];
  bool _isLoading = true;
  int _selectedTab = 0;

  // WebSocket
  WebSocketChannel? _wsChannel;

  // Contact search (inline filtering)
  final TextEditingController _contactSearchController =
      TextEditingController();
  final FocusNode _contactSearchFocus = FocusNode();
  String _contactSearchQuery = '';

  static const String _baseUrl = AppConstants.baseUrl;
  static const String _wsUrl = AppConstants.wsUrl;

  @override
  void initState() {
    super.initState();
    _stars = generateStars();

    _bgController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 20),
    )..repeat();

    _loadConversations();
    _connectWebSocket();
  }

  @override
  void dispose() {
    _bgController.dispose();
    _contactSearchController.dispose();
    _contactSearchFocus.dispose();
    _wsChannel?.sink.close();
    super.dispose();
  }

  void _connectWebSocket() {
    try {
      _wsChannel = WebSocketChannel.connect(Uri.parse(_wsUrl));
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
          data['type'] == 'REQUEST_APPROVED' ||
          data['type'] == 'GROUP_CREATED') {
        _loadConversations();
      }
    } catch (e) {
      debugPrint('WebSocket message parse error: $e');
    }
  }

  Future<void> _loadConversations() async {
    setState(() => _isLoading = true);
    try {
      final userId = widget.user?['id'];
      final res = await http
          .get(
            Uri.parse(
              '$_baseUrl/backend/get_conversations.php?user_id=$userId',
            ),
          )
          .timeout(const Duration(seconds: 10));

      debugPrint('Get conversations response: ${res.body}');

      final data = jsonDecode(res.body);
      if (data['success'] == true) {
        setState(() {
          _conversations = List<Map<String, dynamic>>.from(
            data['conversations'] ?? [],
          );
          _requests = List<Map<String, dynamic>>.from(data['requests'] ?? []);
          _groups = List<Map<String, dynamic>>.from(data['groups'] ?? []);
          _isLoading = false;
        });
      } else {
        debugPrint('Get conversations failed: ${data['message']}');
        setState(() => _isLoading = false);
      }
    } catch (e) {
      setState(() => _isLoading = false);
      debugPrint('Error loading conversations: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: Colors.black,
        extendBody: true,
        body: Stack(
          fit: StackFit.expand,
          children: [
            Container(color: const Color(0xFF0A0A0F)),
            // Wrap animated background in RepaintBoundary to isolate repaints
            RepaintBoundary(
              child: AnimatedBuilder(
                animation: _bgController,
                builder: (_, _) => CustomPaint(
                  painter: NebulaPainter(_bgController.value, _stars),
                  size: Size.infinite,
                ),
              ),
            ),
            SafeArea(
              bottom: false,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildHeader(),
                  _buildTabBar(),
                  Expanded(
                    child: _isLoading
                        ? _buildLoadingState()
                        : _buildConversationsList(),
                  ),
                ],
              ),
            ),
          ],
        ),
        floatingActionButton: null,
        floatingActionButtonLocation: FloatingActionButtonLocation.endFloat,
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 20, 24, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Text(
                'Chats',
                style: TextStyle(
                  fontSize: 40,
                  fontWeight: FontWeight.bold,
                  letterSpacing: -1.2,
                  color: Colors.white,
                  fontFamily: 'SanFranciscoBold',
                  fontStyle: FontStyle.normal,
                ),
              ),
              const Spacer(),
              // Note writing icon - Find People
              _buildHeaderIconButton(Icons.edit_note_rounded, () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => FindPeoplePage(user: widget.user),
                  ),
                ).then((_) => _loadConversations());
              }),
              const SizedBox(width: 8),
              // Photo icon
              _buildHeaderIconButton(Icons.photo_camera_outlined, () {
                // TODO: Implement photo/story
              }),
            ],
          ),
          const SizedBox(height: 16),
          Container(
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.06),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: Colors.white.withValues(alpha: 0.08)),
            ),
            child: TextField(
              controller: _contactSearchController,
              focusNode: _contactSearchFocus,
              style: const TextStyle(color: Colors.white, fontSize: 16),
              decoration: InputDecoration(
                hintText: 'Search contacts',
                hintStyle: TextStyle(
                  color: Colors.white.withValues(alpha: 0.4),
                  fontSize: 16,
                ),
                prefixIcon: Icon(
                  Icons.search,
                  color: Colors.white.withValues(alpha: 0.4),
                  size: 22,
                ),
                suffixIcon: _contactSearchQuery.isNotEmpty
                    ? IconButton(
                        onPressed: () {
                          _contactSearchController.clear();
                          setState(() => _contactSearchQuery = '');
                        },
                        icon: Icon(
                          Icons.close,
                          color: Colors.white.withValues(alpha: 0.4),
                          size: 20,
                        ),
                      )
                    : null,
                border: InputBorder.none,
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 14,
                ),
              ),
              onChanged: (value) {
                setState(
                  () => _contactSearchQuery = value.toLowerCase().trim(),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeaderIconButton(IconData icon, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.08),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: Colors.white.withOpacity(0.1)),
        ),
        child: Icon(icon, color: Colors.white.withOpacity(0.8), size: 24),
      ),
    );
  }

  Widget _buildTabBar() {
    final tabs = ['All', 'Requests', 'Groups'];
    return Container(
      height: 44,
      margin: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
      child: Row(
        children: List.generate(tabs.length, (index) {
          final isSelected = _selectedTab == index;
          final hasNotification = index == 1 && _requests.isNotEmpty;

          return GestureDetector(
            onTap: () => setState(() => _selectedTab = index),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              margin: const EdgeInsets.only(right: 10),
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
              decoration: BoxDecoration(
                color: isSelected
                    ? const Color(0xFF007AFF)
                    : Colors.white.withValues(alpha: 0.06),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  Text(
                    tabs[index],
                    style: TextStyle(
                      color: isSelected
                          ? Colors.white
                          : Colors.white.withValues(alpha: 0.5),
                      fontWeight: isSelected
                          ? FontWeight.w600
                          : FontWeight.w500,
                      fontSize: 14,
                    ),
                  ),
                  if (hasNotification) ...[
                    const SizedBox(width: 6),
                    Container(
                      width: 18,
                      height: 18,
                      decoration: const BoxDecoration(
                        color: Color(0xFFFF3B5C),
                        shape: BoxShape.circle,
                      ),
                      child: Center(
                        child: Text(
                          '${_requests.length}',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          );
        }),
      ),
    );
  }

  Widget _buildLoadingState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          SizedBox(
            width: 28,
            height: 28,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              valueColor: AlwaysStoppedAnimation(
                Colors.white.withValues(alpha: 0.4),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'Loading...',
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 14,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildConversationsList() {
    List<Map<String, dynamic>> displayList;

    switch (_selectedTab) {
      case 1:
        displayList = _requests;
        break;
      case 2:
        displayList = _groups;
        break;
      default:
        // Combine conversations and groups for "All" tab, sorted by last message time
        displayList = [
          ..._conversations,
          ..._groups.map((g) => {...g, 'member_count': g['member_count'] ?? 0}),
        ];
        // Sort by last message time
        displayList.sort((a, b) {
          final aTime = a['last_time'] ?? a['created_at'] ?? '';
          final bTime = b['last_time'] ?? b['created_at'] ?? '';
          return bTime.compareTo(aTime);
        });
    }

    // Apply contact search filter
    if (_contactSearchQuery.isNotEmpty) {
      displayList = displayList.where((item) {
        final name = (item['username'] ?? item['name'] ?? '')
            .toString()
            .toLowerCase();
        final lastMsg = (item['last_message'] ?? '').toString().toLowerCase();
        return name.contains(_contactSearchQuery) ||
            lastMsg.contains(_contactSearchQuery);
      }).toList();
    }

    if (displayList.isEmpty) {
      if (_contactSearchQuery.isNotEmpty) {
        return _buildNoSearchResultsState();
      }
      return _buildEmptyState();
    }

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
      physics: const BouncingScrollPhysics(),
      itemCount: displayList.length,
      itemBuilder: (context, index) {
        return _buildConversationTile(displayList[index], index);
      },
    );
  }

  Widget _buildNoSearchResultsState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.search_off,
            size: 56,
            color: Colors.white.withOpacity(0.15),
          ),
          const SizedBox(height: 16),
          Text(
            'No contacts found',
            style: TextStyle(
              color: Colors.white.withOpacity(0.5),
              fontSize: 17,
              fontWeight: FontWeight.bold,
              fontFamily: 'SanFranciscoBold',
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Try a different search term',
            style: TextStyle(
              color: Colors.white.withOpacity(0.3),
              fontSize: 14,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState() {
    String title;
    String subtitle;

    switch (_selectedTab) {
      case 1:
        title = 'No requests';
        subtitle = 'New message requests appear here';
        break;
      case 2:
        title = 'No groups';
        subtitle = 'Create a group to start';
        break;
      default:
        title = 'No conversations';
        subtitle = 'Tap + to start chatting';
    }

    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // Animated message bubbles icon
          _AnimatedChatBubblesIcon(key: ValueKey(_selectedTab)),
          const SizedBox(height: 24),
          Text(
            title,
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.bold,
              color: Colors.white,
              fontFamily: 'SanFranciscoBold',
            ),
          ),
          const SizedBox(height: 8),
          Text(
            subtitle,
            style: TextStyle(
              fontSize: 14,
              color: Colors.white.withValues(alpha: 0.4),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildConversationTile(Map<String, dynamic> conversation, int index) {
    final username = conversation['username'] ?? 'Unknown';
    final lastMessage = conversation['last_message'] ?? '';
    final profilePic = conversation['profile_picture'];
    final lastTime = conversation['last_time'];
    final isOnline = _isUserOnline(conversation['last_active_at']);
    final isGroup = conversation.containsKey('member_count');

    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => ChatDetailPage(
              user: widget.user,
              peer: conversation,
              isGroup: isGroup,
            ),
          ),
        ).then((_) => _loadConversations());
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.04),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(
          children: [
            Stack(
              children: [
                Container(
                  width: 52,
                  height: 52,
                  decoration: BoxDecoration(
                    color: const Color(0xFF007AFF).withOpacity(0.2),
                    shape: BoxShape.circle,
                  ),
                  child: ClipOval(
                    child: profilePic != null && profilePic.isNotEmpty
                        ? Image.network(
                            profilePic.startsWith('http')
                                ? profilePic
                                : '$_baseUrl/$profilePic',
                            fit: BoxFit.cover,
                            errorBuilder: (_, _, _) =>
                                _buildAvatarFallback(username, isGroup),
                          )
                        : _buildAvatarFallback(username, isGroup),
                  ),
                ),
                if (isOnline && !isGroup)
                  Positioned(
                    right: 0,
                    bottom: 0,
                    child: Container(
                      width: 14,
                      height: 14,
                      decoration: BoxDecoration(
                        color: const Color(0xFF34C759),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: const Color(0xFF0A0A0F),
                          width: 2.5,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          isGroup
                              ? (conversation['name'] ?? 'Group')
                              : username,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (lastTime != null)
                        Text(
                          _formatTime(lastTime),
                          style: TextStyle(
                            color: Colors.white.withOpacity(0.3),
                            fontSize: 12,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 5),
                  Text(
                    lastMessage.isEmpty ? 'Start chatting' : lastMessage,
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.4),
                      fontSize: 14,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Icon(
              Icons.chevron_right,
              color: Colors.white.withOpacity(0.2),
              size: 20,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAvatarFallback(String name, bool isGroup) {
    return Container(
      color: const Color(0xFF007AFF).withOpacity(0.3),
      child: Center(
        child: isGroup
            ? const FaIcon(
                FontAwesomeIcons.userGroup,
                size: 20,
                color: Colors.white70,
              )
            : Text(
                name.isNotEmpty ? name[0].toUpperCase() : '?',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                ),
              ),
      ),
    );
  }

  bool _isUserOnline(String? lastActiveAt) {
    if (lastActiveAt == null) return false;
    try {
      final lastActive = DateTime.parse(lastActiveAt);
      return DateTime.now().difference(lastActive).inMinutes < 5;
    } catch (e) {
      return false;
    }
  }

  String _formatTime(String timeStr) {
    try {
      final time = DateTime.parse(timeStr);
      final diff = DateTime.now().difference(time);
      if (diff.inMinutes < 1) return 'now';
      if (diff.inMinutes < 60) return '${diff.inMinutes}m';
      if (diff.inHours < 24) return '${diff.inHours}h';
      if (diff.inDays < 7) return '${diff.inDays}d';
      return '${time.day}/${time.month}';
    } catch (e) {
      return '';
    }
  }
}

// ============================================================================
// PREMIUM ANIMATED ICONS
// ============================================================================

/// Gorgeous chat bubbles with satisfying bounce physics
class _AnimatedChatBubblesIcon extends StatefulWidget {
  const _AnimatedChatBubblesIcon({super.key});

  @override
  State<_AnimatedChatBubblesIcon> createState() =>
      _AnimatedChatBubblesIconState();
}

class _AnimatedChatBubblesIconState extends State<_AnimatedChatBubblesIcon>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1800),
    )..forward();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 120,
      height: 90,
      child: AnimatedBuilder(
        animation: _controller,
        builder: (_, _) {
          final t = _controller.value;

          // Bubble 1 (blue, left) - bounces in from bottom
          double b1Scale, b1Y, b1Rotation;
          if (t < 0.25) {
            // Launch up
            final p = Curves.easeOut.transform(t / 0.25);
            b1Scale = p * 1.15;
            b1Y = 40 * (1 - p);
            b1Rotation = -0.1 * (1 - p);
          } else if (t < 0.4) {
            // Overshoot and settle
            final p = (t - 0.25) / 0.15;
            b1Scale = 1.15 - (0.15 * Curves.bounceOut.transform(p));
            b1Y = -5 * math.sin(p * math.pi);
            b1Rotation = 0.05 * math.sin(p * math.pi * 2);
          } else {
            b1Scale = 1.0;
            b1Y = 0;
            b1Rotation = 0;
          }

          // Bubble 2 (white, right) - bounces in with delay
          double b2Scale, b2Y, b2Rotation;
          if (t < 0.2) {
            b2Scale = 0;
            b2Y = 30;
            b2Rotation = 0;
          } else if (t < 0.45) {
            final p = Curves.easeOut.transform((t - 0.2) / 0.25);
            b2Scale = p * 1.12;
            b2Y = 30 * (1 - p);
            b2Rotation = 0.1 * (1 - p);
          } else if (t < 0.6) {
            final p = (t - 0.45) / 0.15;
            b2Scale = 1.12 - (0.12 * Curves.bounceOut.transform(p));
            b2Y = -4 * math.sin(p * math.pi);
            b2Rotation = -0.04 * math.sin(p * math.pi * 2);
          } else {
            b2Scale = 1.0;
            b2Y = 0;
            b2Rotation = 0;
          }

          // Wiggle at the end
          double wiggle1 = 0, wiggle2 = 0;
          if (t > 0.7 && t < 0.95) {
            final p = (t - 0.7) / 0.25;
            wiggle1 = math.sin(p * math.pi * 4) * 3 * (1 - p);
            wiggle2 = math.sin(p * math.pi * 4 + 1) * 3 * (1 - p);
          }

          return Stack(
            clipBehavior: Clip.none,
            children: [
              // Left bubble (blue, sender)
              Positioned(
                left: 5 + wiggle1,
                bottom: 10 + b1Y,
                child: Transform.rotate(
                  angle: b1Rotation,
                  child: Transform.scale(
                    scale: b1Scale.clamp(0.0, 2.0),
                    alignment: Alignment.bottomLeft,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 10,
                      ),
                      decoration: BoxDecoration(
                        color: const Color(0xFF007AFF),
                        borderRadius: const BorderRadius.only(
                          topLeft: Radius.circular(18),
                          topRight: Radius.circular(18),
                          bottomRight: Radius.circular(18),
                          bottomLeft: Radius.circular(4),
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          _buildDot(0.9),
                          const SizedBox(width: 4),
                          _buildDot(0.7),
                          const SizedBox(width: 4),
                          _buildDot(0.5),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
              // Right bubble (light, receiver)
              Positioned(
                right: 5 + wiggle2,
                top: 5 + b2Y,
                child: Transform.rotate(
                  angle: b2Rotation,
                  child: Transform.scale(
                    scale: b2Scale.clamp(0.0, 2.0),
                    alignment: Alignment.topRight,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 10,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.15),
                        borderRadius: const BorderRadius.only(
                          topLeft: Radius.circular(18),
                          topRight: Radius.circular(18),
                          bottomLeft: Radius.circular(18),
                          bottomRight: Radius.circular(4),
                        ),
                        border: Border.all(
                          color: Colors.white.withOpacity(0.2),
                          width: 1,
                        ),
                      ),
                      child: Icon(
                        Icons.favorite_rounded,
                        size: 18,
                        color: Colors.white.withOpacity(0.6),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildDot(double opacity) {
    return Container(
      width: 8,
      height: 8,
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(opacity),
        shape: BoxShape.circle,
      ),
    );
  }
}
