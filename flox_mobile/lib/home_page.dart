import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'dart:ui' as ui;
import 'dart:math';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'stars.dart';
import 'chats_page.dart';
import 'video_player_page.dart';
import 'constants.dart';
import 'notification_service.dart';
import 'create_content_page.dart';
import 'you_page.dart';
import 'clips_page.dart';

class HomePage extends StatefulWidget {
  final Map<String, dynamic>? user;
  const HomePage({super.key, this.user});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> with TickerProviderStateMixin {
  late AnimationController _bgController;
  late List<Star> _stars;

  List<Map<String, dynamic>> _videos = [];
  bool _isLoading = true;
  String? _error;
  int _currentIndex = 0;

  final ScrollController _scrollController = ScrollController();
  final PageController _pageController = PageController();

  final Map<int, String> _tabLabels = {
    0: 'Home',
    1: 'Chats',
    3: 'Clips',
    4: 'You',
  };

  @override
  void initState() {
    super.initState();
    _stars = generateStars();
    _bgController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 20),
    )..repeat();

    _loadVideos();
  }

  @override
  void dispose() {
    _bgController.dispose();
    _scrollController.dispose();
    _pageController.dispose();
    super.dispose();
  }

  Future<void> _loadVideos() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final url = '${AppConstants.baseUrl}/backend/getVideos.php';
      debugPrint('Loading videos from: $url');

      final res = await http
          .get(Uri.parse(url))
          .timeout(const Duration(seconds: 10)); // Faster timeout

      debugPrint('Response status: ${res.statusCode}');
      debugPrint('Response body: ${res.body}');

      if (res.statusCode != 200) {
        throw Exception('HTTP ${res.statusCode}');
      }

      final data = jsonDecode(res.body);
      if (data['success'] == true && data['videos'] != null) {
        final videos = List<Map<String, dynamic>>.from(data['videos']);
        setState(() {
          _videos = videos;
          _isLoading = false;
        });
      } else {
        setState(() {
          _error = data['message'] ?? 'Failed to load videos';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _error = 'Connect Error (${AppConstants.serverIp}):\n$e';
        _isLoading = false;
      });
      debugPrint('Error loading videos: $e');
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
            // Gradient background
            Container(
              decoration: const BoxDecoration(
                gradient: RadialGradient(
                  center: Alignment(-0.4, -0.6),
                  radius: 1.2,
                  colors: [Color(0xFF0A0A1A), Color(0xFF000000)],
                ),
              ),
            ),
            // Blue accent glow
            Container(
              decoration: BoxDecoration(
                gradient: RadialGradient(
                  center: const Alignment(0.4, -0.4),
                  radius: 1.0,
                  colors: [
                    const Color(0xFF0071E3).withOpacity(0.08),
                    Colors.transparent,
                  ],
                ),
              ),
            ),
            // Stars - Wrapped in RepaintBoundary for isolation
            RepaintBoundary(
              child: AnimatedBuilder(
                animation: _bgController,
                builder: (_, _) => CustomPaint(
                  painter: NebulaPainter(_bgController.value, _stars),
                  size: Size.infinite,
                ),
              ),
            ),
            // Main content - header only on home tab
            Column(
              children: [
                // Only show header on Home tab (index 0)
                if (_currentIndex == 0) _buildHeader(),
                Expanded(
                  child: AnimatedSwitcher(
                    duration: const Duration(milliseconds: 300),
                    child: _isLoading
                        ? _buildLoadingState()
                        : _videos.isEmpty
                        ? _buildEmptyState()
                        : _buildBodyContent(),
                  ),
                ),
              ],
            ),
          ],
        ),
        bottomNavigationBar: _buildBottomNav(),
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 16,
        top: MediaQuery.of(context).padding.top + 12,
        bottom: 8,
      ),
      child: Row(
        children: [
          // Videos Title (big and bold like Chats/Settings)
          const Text(
            'Videos',
            style: TextStyle(
              fontSize: 34,
              fontWeight: FontWeight.bold,
              letterSpacing: -0.5,
            ),
          ),
          const Spacer(),
          // Search button
          _glassIconButton(Icons.search_rounded, () {
            _showSnackBar('Search coming soon');
          }),
          const SizedBox(width: 8),
          // Notifications button
          _glassIconButton(Icons.notifications_outlined, () {
            NotificationService().showInstantNotification(
              title: 'Loop',
              body: 'Notification system is active!',
            );
          }),
        ],
      ),
    );
  }

  Widget _glassIconButton(IconData icon, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: BackdropFilter(
          filter: ui.ImageFilter.blur(sigmaX: 10, sigmaY: 10),
          child: Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.08),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.white.withOpacity(0.1)),
            ),
            child: Icon(icon, color: Colors.white.withOpacity(0.8), size: 22),
          ),
        ),
      ),
    );
  }

  Widget _buildLoadingState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          SizedBox(
            width: 48,
            height: 48,
            child: CircularProgressIndicator(
              strokeWidth: 3,
              valueColor: AlwaysStoppedAnimation(
                const Color(0xFF0071E3).withOpacity(0.8),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'Loading videos...',
            style: TextStyle(
              color: Colors.white.withOpacity(0.5),
              fontSize: 14,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 40),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            ShaderMask(
              shaderCallback: (bounds) => const LinearGradient(
                colors: [Color(0xFF0071E3), Color(0xFF00F2FF)],
              ).createShader(bounds),
              child: Icon(
                _error != null ? Icons.error_outline : Icons.movie_outlined,
                size: 80,
                color: Colors.white,
              ),
            ),
            const SizedBox(height: 24),
            Text(
              _error != null ? 'Oops!' : 'No videos yet',
              style: const TextStyle(
                fontSize: 28,
                fontWeight: FontWeight.bold,
                letterSpacing: -0.8,
                fontFamily: 'SanFranciscoBold',
              ),
            ),
            const SizedBox(height: 8),
            Text(
              _error ?? 'Be the first to upload something amazing!',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 15,
                color: Colors.white.withOpacity(0.5),
              ),
            ),
            const SizedBox(height: 32),
            _buildPrimaryButton('Refresh', _loadVideos),
          ],
        ),
      ),
    );
  }

  Widget _buildBodyContent() {
    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 400),
      switchInCurve: Curves.easeOutCubic,
      switchOutCurve: Curves.easeInCubic,
      transitionBuilder: (Widget child, Animation<double> animation) {
        return FadeTransition(
          opacity: animation,
          child: SlideTransition(
            position: Tween<Offset>(
              begin: const Offset(0, 0.02),
              end: Offset.zero,
            ).animate(animation),
            child: child,
          ),
        );
      },
      child: _currentIndex == 0
          ? _buildVideoFeed(key: const ValueKey('home_feed'))
          : _currentIndex == 1
          ? ChatsPage(key: const ValueKey('chats_page'), user: widget.user)
          : _currentIndex == 3
          ? const ClipsPage(key: ValueKey('clips_page'))
          : _currentIndex == 4
          ? YouPage(key: const ValueKey('you_page'), user: widget.user)
          : Container(
              key: ValueKey('tab_$_currentIndex'),
              alignment: Alignment.center,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  FaIcon(
                    FontAwesomeIcons.bolt,
                    size: 40,
                    color: Colors.white.withOpacity(0.1),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    '${_tabLabels[_currentIndex]} Section Coming Soon',
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.5),
                      fontSize: 18,
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _buildVideoFeed({Key? key}) {
    if (_videos.isEmpty && !_isLoading) return _buildEmptyState();

    return RefreshIndicator(
      key: key,
      onRefresh: _loadVideos,
      color: const Color(0xFF0071E3),
      backgroundColor: const Color(0xFF1A1A25),
      child: ListView.builder(
        padding: const EdgeInsets.only(bottom: 120),
        itemCount: _videos.length + 2, // +1 for featured, +1 for section header
        itemBuilder: (context, index) {
          if (index == 0) return _buildFeaturedCinema();
          if (index == 1) {
            return _buildSectionHeader("TRENDING NOW", Icons.bolt);
          }
          return _buildFullWidthVideoCard(_videos[index - 2], index - 2);
        },
      ),
    );
  }

  Widget _buildFeaturedCinema() {
    if (_videos.isEmpty) return const SizedBox.shrink();
    final featured = _videos[0];

    return Container(
      height: 380,
      margin: const EdgeInsets.only(bottom: 32, top: 0),
      child: Stack(
        children: [
          // Background Backdrop
          Positioned.fill(
            child: ShaderMask(
              shaderCallback: (rect) {
                return LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Colors.black.withOpacity(0.5), Colors.black],
                  stops: const [0.4, 1.0],
                ).createShader(rect);
              },
              blendMode: BlendMode.dstIn,
              child: _buildThumbnail(featured),
            ),
          ),
          // Content
          Positioned(
            left: 20,
            right: 20,
            bottom: 30,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0071E3).withOpacity(0.1),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: const Color(0xFF0071E3).withOpacity(0.5),
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        Icons.stars,
                        color: Color(0xFF0071E3),
                        size: 14,
                      ),
                      const SizedBox(width: 6),
                      Text(
                        'FEATURED CONTENT',
                        style: TextStyle(
                          color: const Color(0xFF0071E3),
                          fontSize: 10,
                          fontWeight: FontWeight.w900,
                          letterSpacing: 1.2,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  featured['title'] ?? 'Untitled',
                  style: const TextStyle(
                    fontSize: 34,
                    fontWeight: FontWeight.w900,
                    height: 1.1,
                    letterSpacing: -1,
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    CircleAvatar(
                      radius: 10,
                      backgroundColor: const Color(0xFF0071E3),
                      child: Text(
                        (featured['author']?['username'] ?? 'U')
                            .toString()
                            .substring(0, 1)
                            .toUpperCase(),
                        style: const TextStyle(
                          fontSize: 8,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      '@${featured['author']?['username'] ?? 'unknown'}',
                      style: TextStyle(
                        color: Colors.white.withOpacity(0.7),
                        fontSize: 13,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Text(
                      '•  ${_formatViews(featured['views'])} views',
                      style: TextStyle(
                        color: Colors.white.withOpacity(0.4),
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 24),
                GestureDetector(
                  onTap: () => _showSnackBar('Opening Cinema...'),
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 24,
                      vertical: 12,
                    ),
                    decoration: BoxDecoration(
                      color: const Color(0xFF0071E3),
                      borderRadius: BorderRadius.circular(12),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF0071E3).withOpacity(0.4),
                          blurRadius: 20,
                          offset: const Offset(0, 10),
                        ),
                      ],
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: const [
                        Icon(Icons.play_arrow_rounded, color: Colors.white),
                        SizedBox(width: 8),
                        Text(
                          'Watch Now',
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 15,
                          ),
                        ),
                      ],
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

  Widget _buildSectionHeader(String title, IconData icon) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: const Color(0xFF0071E3).withOpacity(0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: const Color(0xFF0071E3), size: 18),
          ),
          const SizedBox(width: 12),
          Text(
            title,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w900,
              letterSpacing: 1.5,
              color: Colors.white,
            ),
          ),
          const Spacer(),
          Text(
            'SEE ALL',
            style: TextStyle(
              color: Colors.white.withOpacity(0.3),
              fontSize: 11,
              fontWeight: FontWeight.w800,
              letterSpacing: 1,
            ),
          ),
          Icon(
            Icons.chevron_right,
            color: Colors.white.withOpacity(0.2),
            size: 16,
          ),
        ],
      ),
    );
  }

  Widget _buildFullWidthVideoCard(Map<String, dynamic> video, int index) {
    return Container(
      margin: const EdgeInsets.only(bottom: 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Author Header
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            child: Row(
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '@${video['author']?['username'] ?? 'unknown'}',
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                      ),
                    ),
                    Text(
                      _timeAgo(video['created_at']),
                      style: TextStyle(
                        color: Colors.white.withOpacity(0.4),
                        fontSize: 10,
                      ),
                    ),
                  ],
                ),
                const Spacer(),
                Icon(
                  Icons.more_horiz,
                  color: Colors.white.withOpacity(0.3),
                  size: 20,
                ),
              ],
            ),
          ),

          // Glassmorphic Thumbnail Container
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: GestureDetector(
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (context) =>
                        VideoPlayerPage(video: video, user: widget.user),
                  ),
                );
              },
              child: ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: Container(
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.white.withOpacity(0.08)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.5),
                        blurRadius: 20,
                      ),
                    ],
                  ),
                  child: AspectRatio(
                    aspectRatio: 16 / 9,
                    child: Stack(
                      children: [
                        Positioned.fill(child: _buildThumbnail(video)),
                        // HD Badge
                        Positioned(
                          top: 12,
                          right: 12,
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 6,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.black.withOpacity(0.6),
                              borderRadius: BorderRadius.circular(4),
                              border: Border.all(
                                color: const Color(0xFF0071E3).withOpacity(0.3),
                              ),
                            ),
                            child: const Text(
                              'HD',
                              style: TextStyle(
                                color: Color(0xFF0071E3),
                                fontSize: 9,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                        ),
                        Positioned.fill(
                          child: Center(
                            child: ClipOval(
                              child: BackdropFilter(
                                filter: ui.ImageFilter.blur(
                                  sigmaX: 4,
                                  sigmaY: 4,
                                ),
                                child: Container(
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(
                                    color: Colors.black.withOpacity(0.2),
                                    shape: BoxShape.circle,
                                    border: Border.all(
                                      color: Colors.white.withOpacity(0.1),
                                    ),
                                  ),
                                  child: const Icon(
                                    Icons.play_arrow_rounded,
                                    color: Colors.white,
                                    size: 36,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                        // Views Badge
                        Positioned(
                          left: 12,
                          bottom: 12,
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(6),
                            child: BackdropFilter(
                              filter: ui.ImageFilter.blur(
                                sigmaX: 10,
                                sigmaY: 10,
                              ),
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.black.withOpacity(0.4),
                                  borderRadius: BorderRadius.circular(6),
                                  border: Border.all(
                                    color: Colors.white.withOpacity(0.1),
                                  ),
                                ),
                                child: Row(
                                  children: [
                                    const Icon(
                                      Icons.remove_red_eye_rounded,
                                      size: 12,
                                      color: Colors.white70,
                                    ),
                                    const SizedBox(width: 4),
                                    Text(
                                      _formatViews(video['views']),
                                      style: const TextStyle(
                                        fontSize: 10,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),

          // Video Title & Author Info
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  video['title'] ?? 'Untitled Video',
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    fontFamily: 'SanFranciscoBold',
                    fontStyle: FontStyle.normal,
                  ),
                ),
                if (video['description'] != null &&
                    video['description'].isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Text(
                      video['description'],
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: Colors.white.withOpacity(0.5),
                        fontSize: 12,
                      ),
                    ),
                  ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    CircleAvatar(
                      radius: 12,
                      backgroundColor: const Color(0xFF0071E3),
                      child: Text(
                        (video['author']?['username'] ?? 'U')
                            .toString()
                            .substring(0, 1)
                            .toUpperCase(),
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 10,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      '@${video['author']?['username'] ?? 'unknown'}',
                      style: TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                        color: Colors.white.withOpacity(0.7),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildThumbnail(Map<String, dynamic> video) {
    final thumbnailUrl = video['thumbnail_url']?.toString();

    if (thumbnailUrl == null ||
        thumbnailUrl.isEmpty ||
        thumbnailUrl == 'null') {
      return _thumbnailPlaceholder();
    }

    // Construct full URL
    String fullUrl;
    if (thumbnailUrl.startsWith('http://') ||
        thumbnailUrl.startsWith('https://')) {
      fullUrl = thumbnailUrl;
    } else {
      // Remove leading slash if present
      final cleanPath = thumbnailUrl.startsWith('/')
          ? thumbnailUrl.substring(1)
          : thumbnailUrl;
      fullUrl = '${AppConstants.baseUrl}/$cleanPath';
    }

    return Image.network(
      fullUrl,
      fit: BoxFit.cover,
      loadingBuilder: (context, child, loadingProgress) {
        if (loadingProgress == null) return child;
        return Container(
          color: const Color(0xFF1A1A25),
          child: Center(
            child: CircularProgressIndicator(
              value: loadingProgress.expectedTotalBytes != null
                  ? loadingProgress.cumulativeBytesLoaded /
                        loadingProgress.expectedTotalBytes!
                  : null,
              strokeWidth: 2,
              valueColor: AlwaysStoppedAnimation(
                const Color(0xFF0071E3).withOpacity(0.5),
              ),
            ),
          ),
        );
      },
      errorBuilder: (context, error, stackTrace) {
        debugPrint('Thumbnail load error: $error for URL: $fullUrl');
        return _thumbnailPlaceholder();
      },
    );
  }

  Widget _thumbnailPlaceholder() {
    return Container(
      color: const Color(0xFF1A1A25),
      child: Center(
        child: Icon(
          Icons.play_circle_outline,
          size: 40,
          color: Colors.white.withOpacity(0.3),
        ),
      ),
    );
  }

  Widget _buildBottomNav() {
    return Container(
      margin: EdgeInsets.fromLTRB(
        40, // Smaller width (more margin)
        0,
        40,
        MediaQuery.of(context).padding.bottom + 10,
      ),
      child: _SwipeableNavigation(
        currentIndex: _currentIndex,
        onItemSelected: (index) {
          if (index == 2) {
            // Create button index
            HapticFeedback.mediumImpact();
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const CreateContentPage()),
            );
          } else {
            setState(() => _currentIndex = index);
          }
        },
      ),
    );
  }

  Widget _buildPrimaryButton(String text, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 14),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFF0088FF), Color(0xFF0055DD)],
          ),
          borderRadius: BorderRadius.circular(14),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF0071E3).withOpacity(0.4),
              blurRadius: 20,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Text(
          text,
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
        ),
      ),
    );
  }

  void _showSnackBar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: const Color(0xFF1A1A25),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );
  }

  String _formatViews(dynamic views) {
    if (views == null) return '0 views';
    final count = int.tryParse(views.toString()) ?? 0;
    if (count >= 1000000) {
      return '${(count / 1000000).toStringAsFixed(1)}M views';
    } else if (count >= 1000) {
      return '${(count / 1000).toStringAsFixed(1)}K views';
    }
    return '$count views';
  }

  String _timeAgo(dynamic dateStr) {
    if (dateStr == null) return '';
    try {
      final date = DateTime.parse(dateStr.toString());
      final diff = DateTime.now().difference(date);
      if (diff.inDays > 365) return '${diff.inDays ~/ 365}y ago';
      if (diff.inDays > 30) return '${diff.inDays ~/ 30}mo ago';
      if (diff.inDays > 0) return '${diff.inDays}d ago';
      if (diff.inHours > 0) return '${diff.inHours}h ago';
      if (diff.inMinutes > 0) return '${diff.inMinutes}m ago';
      return 'just now';
    } catch (_) {
      return '';
    }
  }
}

// Star background painter (reused from main.dart)

// Swipeable Navigation Widget
class _SwipeableNavigation extends StatefulWidget {
  final int currentIndex;
  final ValueChanged<int> onItemSelected;

  const _SwipeableNavigation({
    required this.currentIndex,
    required this.onItemSelected,
  });

  @override
  State<_SwipeableNavigation> createState() => _SwipeableNavigationState();
}

class _SwipeableNavigationState extends State<_SwipeableNavigation> {
  // 0: Home, 1: Messages, 2: Create, 3: Clips, 4: You
  // Mapping external indices to internal 0-4 range
  int _internalIndex = 0;
  bool _isPressed = false;

  @override
  void initState() {
    super.initState();
    _internalIndex = _mapExternalToInternal(widget.currentIndex);
  }

  @override
  void didUpdateWidget(_SwipeableNavigation oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.currentIndex != oldWidget.currentIndex) {
      _internalIndex = _mapExternalToInternal(widget.currentIndex);
    }
  }

  int _mapExternalToInternal(int index) {
    return index;
  }

  void _handleDrag(DragUpdateDetails details, double width) {
    final itemWidth = width / 5;
    final newIndex = (details.localPosition.dx / itemWidth).floor();
    final clampedIndex = newIndex.clamp(0, 4);

    if (clampedIndex != _internalIndex) {
      HapticFeedback.selectionClick();
      setState(() => _internalIndex = clampedIndex);
      // Don't call onItemSelected here to avoid spamming navigation events
    }
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final totalWidth = constraints.maxWidth;
        final itemWidth = totalWidth / 5;
        // height 60 -> smaller than previous 70
        const height = 60.0;

        return GestureDetector(
          onHorizontalDragDown: (_) => setState(() => _isPressed = true),
          onHorizontalDragCancel: () => setState(() => _isPressed = false),
          onHorizontalDragEnd: (_) {
            setState(() => _isPressed = false);
            widget.onItemSelected(_internalIndex);
            // Snap back if index 2 (Create) since it's an action, not a persistent tab
            if (_internalIndex == 2) {
              Future.delayed(const Duration(milliseconds: 500), () {
                if (mounted) {
                  setState(() => _internalIndex = widget.currentIndex);
                }
              });
            }
          },
          onHorizontalDragUpdate: (details) => _handleDrag(details, totalWidth),
          onTapDown: (_) => setState(() => _isPressed = true),
          onTapUp: (details) {
            setState(() => _isPressed = false);
            final index = (details.localPosition.dx / itemWidth).floor().clamp(
              0,
              4,
            );
            // Update internal state immediately for tap
            setState(() => _internalIndex = index);
            widget.onItemSelected(index);
            // Snap back for Create button
            if (index == 2) {
              Future.delayed(const Duration(milliseconds: 500), () {
                if (mounted) {
                  setState(() => _internalIndex = widget.currentIndex);
                }
              });
            }
          },
          child: ClipRRect(
            borderRadius: BorderRadius.circular(30),
            child: BackdropFilter(
              filter: ui.ImageFilter.blur(sigmaX: 20, sigmaY: 20),
              child: Container(
                height: height,
                decoration: BoxDecoration(
                  color: Colors.black.withOpacity(0.6), // Darker background
                  borderRadius: BorderRadius.circular(30),
                  border: Border.all(
                    color: Colors.white.withOpacity(0.1),
                    width: 0.5,
                  ),
                ),
                child: Stack(
                  children: [
                    // Moving Selection Box
                    AnimatedPositioned(
                      duration: const Duration(milliseconds: 250),
                      curve: Curves.easeOutBack,
                      left: _internalIndex * itemWidth,
                      top: 5,
                      bottom: 5,
                      width: itemWidth,
                      child: Center(
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          width: _internalIndex == 2
                              ? (_isPressed ? 55 : 48)
                              : (_isPressed ? 45 : 38),
                          height: _internalIndex == 2
                              ? (_isPressed ? 55 : 48)
                              : (_isPressed ? 45 : 38),
                          decoration: BoxDecoration(
                            gradient: _internalIndex == 2
                                ? const LinearGradient(
                                    colors: [
                                      Color(0xFF0071E3),
                                      Color(0xFF00D4FF),
                                    ],
                                    begin: Alignment.topLeft,
                                    end: Alignment.bottomRight,
                                  )
                                : null,
                            color: _internalIndex == 2
                                ? null
                                : const Color(0xFF0071E3),
                            borderRadius: BorderRadius.circular(
                              _internalIndex == 2 ? 24 : 14,
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: const Color(0xFF0071E3).withOpacity(0.4),
                                blurRadius: 10,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),

                    // Icons
                    Row(
                      children: [
                        _buildAnimatedIcon(0, FontAwesomeIcons.house),
                        _buildAnimatedIcon(1, FontAwesomeIcons.comments),
                        _buildAnimatedIcon(2, FontAwesomeIcons.plus), // Create
                        _buildAnimatedIcon(3, FontAwesomeIcons.clapperboard),
                        _buildAnimatedIcon(4, FontAwesomeIcons.user),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildAnimatedIcon(int index, IconData icon) {
    return Expanded(
      child: Center(
        child: _FooterIcon(
          icon: icon,
          isSelected: _internalIndex == index,
          isCreate: index == 2,
        ),
      ),
    );
  }
}

class _FooterIcon extends StatefulWidget {
  final IconData icon;
  final bool isSelected;
  final bool isCreate;

  const _FooterIcon({
    required this.icon,
    required this.isSelected,
    this.isCreate = false,
  });

  @override
  State<_FooterIcon> createState() => _FooterIconState();
}

class _FooterIconState extends State<_FooterIcon>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _scaleAnimation;
  late Animation<double> _rotateAnimation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      duration: const Duration(milliseconds: 400),
      vsync: this,
    );

    // Bounce effect: 1.0 -> 0.8 -> 1.2 -> 1.0
    _scaleAnimation = TweenSequence<double>([
      TweenSequenceItem(tween: Tween(begin: 1.0, end: 0.8), weight: 30),
      TweenSequenceItem(tween: Tween(begin: 0.8, end: 1.2), weight: 40),
      TweenSequenceItem(tween: Tween(begin: 1.2, end: 1.0), weight: 30),
    ]).animate(CurvedAnimation(parent: _controller, curve: Curves.easeInOut));

    // Slight rotation for some playfulness (optional, keeping small)
    _rotateAnimation = Tween<double>(begin: 0, end: 0).animate(_controller);
  }

  @override
  void didUpdateWidget(_FooterIcon oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.isSelected && !oldWidget.isSelected) {
      _controller.forward(from: 0.0);
      HapticFeedback.lightImpact();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Determine color: Selected -> White, Unselected -> White38
    // Create button is always White
    final color = widget.isCreate
        ? Colors.white
        : (widget.isSelected ? Colors.white : Colors.white.withOpacity(0.5));

    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        return Transform.scale(
          scale: _scaleAnimation.value,
          child: FaIcon(
            widget.icon,
            size: widget.isCreate ? 20 : 18,
            color: color,
          ),
        );
      },
    );
  }
}

// Loop Logo Widget - Vector drawn logo
class LoopLogo extends StatelessWidget {
  const LoopLogo({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomPaint(painter: _LoopLogoPainter(), size: const Size(36, 36));
  }
}

class _LoopLogoPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = Colors.white;
    final scale = size.width / 567;

    canvas.save();
    canvas.scale(scale);

    canvas.save();
    canvas.translate(87.1055, 201.718);
    canvas.rotate(28.3316 * pi / 180);
    canvas.drawRect(const Rect.fromLTWH(0, 0, 115.939, 183.544), paint);
    canvas.restore();
    canvas.save();
    canvas.translate(353.812, 340);
    canvas.rotate(28.3316 * pi / 180);
    canvas.drawRect(const Rect.fromLTWH(0, 0, 115.939, 183.544), paint);
    canvas.restore();
    canvas.save();
    canvas.translate(48.8387, 275);
    canvas.rotate(27.6121 * pi / 180);
    canvas.drawRect(const Rect.fromLTWH(0, 0, 414.63, 101.691), paint);
    canvas.restore();
    canvas.save();
    canvas.translate(481.505, 351.015);
    canvas.rotate(-152.584 * pi / 180);
    canvas.drawRect(const Rect.fromLTWH(0, 0, 115.939, 183.544), paint);
    canvas.restore();
    canvas.save();
    canvas.translate(212.624, 217.011);
    canvas.rotate(-152.584 * pi / 180);
    canvas.drawRect(const Rect.fromLTWH(0, 0, 115.939, 183.544), paint);
    canvas.restore();
    canvas.save();
    canvas.translate(518.597, 277.131);
    canvas.rotate(-153.303 * pi / 180);
    canvas.drawRect(const Rect.fromLTWH(0, 0, 414.63, 101.691), paint);
    canvas.restore();

    final trianglePath = Path()
      ..moveTo(328.646, 283.001)
      ..lineTo(251.957, 323.236)
      ..lineTo(255.457, 236.704)
      ..close();
    canvas.drawPath(trianglePath, paint);
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
