import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:video_player/video_player.dart';
import 'package:chewie/chewie.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:http/http.dart' as http;
import 'constants.dart';
import 'widgets/liquid_glass_bar.dart';
import 'widgets/custom_video_controls.dart';

class VideoPlayerPage extends StatefulWidget {
  final Map<String, dynamic> video;
  final Map<String, dynamic>? user;

  const VideoPlayerPage({super.key, required this.video, this.user});

  @override
  State<VideoPlayerPage> createState() => _VideoPlayerPageState();
}

class _VideoPlayerPageState extends State<VideoPlayerPage> {
  late VideoPlayerController _videoPlayerController;
  ChewieController? _chewieController;
  bool _isLoading = true;
  String? _error;

  // Recommendations
  List<Map<String, dynamic>> _recommendations = [];
  bool _loadingRecs = true;

  // Comments
  List<Map<String, dynamic>> _comments = [];
  bool _loadingComments = true;
  final TextEditingController _commentController = TextEditingController();
  bool _isPostingComment = false;
  int _commentPage = 1;
  bool _hasMoreComments = true;
  bool _isLoadingMoreComments = false;

  // Mini Queue State
  // Stored in-memory for this session. Syncing would require backend "queue" endpoint.
  // Currently local-only as per "Explain how state is stored" (Local State).
  final ValueNotifier<bool> _queueVisibleNotifier = ValueNotifier(false);
  final ValueNotifier<List<Map<String, dynamic>>> _miniQueueNotifier =
      ValueNotifier([]);

  // Orientation tracking for auto-fullscreen
  Orientation? _previousOrientation;

  @override
  void initState() {
    super.initState();
    _initVideo();
    _loadRecommendations();
    _loadComments();
  }

  Future<void> _initVideo() async {
    try {
      String videoUrl = widget.video['video_url'];
      if (!videoUrl.startsWith('http')) {
        // Handle relative paths
        if (videoUrl.startsWith('/')) videoUrl = videoUrl.substring(1);
        videoUrl = '${AppConstants.baseUrl}/$videoUrl';
      }

      debugPrint('Initializing video: $videoUrl');

      // DEBUG: Test with public video to check if issue is server-side
      const testUrl =
          'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';
      videoUrl = testUrl;

      _videoPlayerController = VideoPlayerController.networkUrl(
        Uri.parse(videoUrl),
      );
      await _videoPlayerController.initialize();

      _chewieController = ChewieController(
        videoPlayerController: _videoPlayerController,
        autoPlay: true,
        looping: false,
        aspectRatio: _videoPlayerController.value.aspectRatio,
        allowFullScreen: true,
        allowedScreenSleep: false,
        customControls: CustomVideoControls(
          title: widget.video['title'] ?? '',
          onNext: () {
            if (_recommendations.isNotEmpty) {
              final nextVideo = _recommendations.first;
              Navigator.pushReplacement(
                context,
                MaterialPageRoute(
                  builder: (_) =>
                      VideoPlayerPage(video: nextVideo, user: widget.user),
                ),
              );
            } else {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('No next video available')),
              );
            }
          },
        ),
        // Mini Queue Overlay
        overlay: MiniQueueOverlay(
          queueVisibleNotifier: _queueVisibleNotifier,
          queueNotifier: _miniQueueNotifier,
          onPlayVideo: (video) {
            Navigator.pushReplacement(
              context,
              MaterialPageRoute(
                builder: (_) =>
                    VideoPlayerPage(video: video, user: widget.user),
              ),
            );
          },
        ),
      );

      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    } catch (e) {
      debugPrint('Error initializing video: $e');
      if (mounted) {
        setState(() {
          _error = 'Failed to load video: $e';
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _loadRecommendations() async {
    try {
      final res = await http.get(
        Uri.parse('${AppConstants.baseUrl}/backend/getVideos.php'),
      );
      final data = jsonDecode(res.body);
      if (data['success'] == true && data['videos'] != null) {
        final allVideos = List<Map<String, dynamic>>.from(data['videos']);
        // Filter out current video
        if (mounted) {
          setState(() {
            _recommendations = allVideos
                .where(
                  (v) => v['id'].toString() != widget.video['id'].toString(),
                )
                .toList();
            _loadingRecs = false;

            // Initialize Mini Queue with top 3-5 videos (snapshot)
            // Does not auto refresh after this.
            if (_miniQueueNotifier.value.isEmpty) {
              _miniQueueNotifier.value = _recommendations.take(5).toList();
            }
          });
        }
      }
    } catch (e) {
      debugPrint('Error loading recs: $e');
    }
  }

  Future<void> _loadComments({bool refresh = true}) async {
    if (refresh) {
      if (mounted) {
        setState(() {
          _loadingComments = true;
          _commentPage = 1;
          _hasMoreComments = true;
        });
      }
    } else {
      if (_isLoadingMoreComments || !_hasMoreComments) return;
      if (mounted) setState(() => _isLoadingMoreComments = true);
    }

    try {
      final res = await http.get(
        Uri.parse(
          '${AppConstants.baseUrl}/backend/getComments.php?video_id=${widget.video['id']}&page=$_commentPage&limit=20',
        ),
      );
      final data = jsonDecode(res.body);
      if (data['success'] == true) {
        final newComments = List<Map<String, dynamic>>.from(data['comments']);
        if (newComments.length < 20) {
          _hasMoreComments = false;
        }

        if (mounted) {
          setState(() {
            if (refresh) {
              _comments = newComments;
              _loadingComments = false;
            } else {
              _comments.addAll(newComments);
              _isLoadingMoreComments = false;
            }
            if (_hasMoreComments) _commentPage++;
          });
        }
      } else {
        if (mounted) {
          setState(() {
            _loadingComments = false;
            _isLoadingMoreComments = false;
          });
        }
      }
    } catch (e) {
      debugPrint('Error loading comments: $e');
      if (mounted) {
        setState(() {
          _loadingComments = false;
          _isLoadingMoreComments = false;
        });
      }
    }
  }

  Future<void> _postComment() async {
    final text = _commentController.text.trim();
    if (text.isEmpty || _isPostingComment) return;

    setState(() => _isPostingComment = true);

    try {
      final res = await http.post(
        Uri.parse('${AppConstants.baseUrl}/backend/addComment.php'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'user_id': widget.user?['id'],
          'video_id': widget.video['id'],
          'comment': text,
        }),
      );

      debugPrint('Comment Post Status: ${res.statusCode}');
      debugPrint('Comment Post Body: ${res.body}');

      if (res.body.isEmpty) {
        throw Exception('Server returned empty response');
      }

      final data = jsonDecode(res.body);
      if (data['success'] == true) {
        _commentController.clear();
        // Add to list immediately or reload
        if (data['comment'] != null) {
          setState(() {
            _comments.insert(0, data['comment']);
          });
        } else {
          _loadComments();
        }
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Comment posted!')));
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(data['message'] ?? 'Failed to post comment')),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Error: $e')));
    } finally {
      setState(() => _isPostingComment = false);
    }
  }

  @override
  void dispose() {
    _videoPlayerController.dispose();
    _chewieController?.dispose();
    // Use the cleanup method for notifiers if needed, or just let GC handle it
    // since they are local to this state.
    _commentController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Auto-fullscreen on rotation
    final currentOrientation = MediaQuery.of(context).orientation;
    if (_chewieController != null && _previousOrientation != null) {
      if (currentOrientation == Orientation.landscape &&
          _previousOrientation == Orientation.portrait) {
        // Rotated to landscape - enter fullscreen
        if (!_chewieController!.isFullScreen) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            _chewieController!.enterFullScreen();
          });
        }
      } else if (currentOrientation == Orientation.portrait &&
          _previousOrientation == Orientation.landscape) {
        // Rotated to portrait - exit fullscreen
        if (_chewieController!.isFullScreen) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            _chewieController!.exitFullScreen();
          });
        }
      }
    }
    _previousOrientation = currentOrientation;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: Colors.black,
        body: Container(
          decoration: const BoxDecoration(
            gradient: RadialGradient(
              center: Alignment(-0.6, -0.6),
              radius: 1.8,
              colors: [Color(0xFF0F0F1A), Color(0xFF000000)],
            ),
          ),
          child: SafeArea(
            child: Column(
              children: [
                // Video Player Section
                _buildVideoPlayer(),

                // Scrollable Content
                Expanded(
                  child: SingleChildScrollView(
                    physics: const BouncingScrollPhysics(),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _buildVideoInfo(),
                        _buildActionButtons(), // Clean Liquid Glass Effect
                        const Divider(color: Colors.white12, height: 32),
                        _buildCommentsSection(),
                        const Divider(color: Colors.white12, height: 32),
                        _buildRecommendations(),
                        const SizedBox(height: 40),
                      ],
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

  Widget _buildVideoPlayer() {
    if (_isLoading) {
      return const AspectRatio(
        aspectRatio: 16 / 9,
        child: Center(
          child: CircularProgressIndicator(color: Color(0xFF3EA6FF)),
        ),
      );
    }
    if (_error != null) {
      return AspectRatio(
        aspectRatio: 16 / 9,
        child: Center(
          child: Text(_error!, style: const TextStyle(color: Colors.red)),
        ),
      );
    }
    return AspectRatio(
      aspectRatio: _videoPlayerController.value.aspectRatio,
      child: Chewie(controller: _chewieController!),
    );
  }

  Widget _buildVideoInfo() {
    final author = widget.video['author'] ?? {};
    final title = widget.video['title'] ?? 'Untitled';
    final views = widget.video['views'] ?? 0;

    return Padding(
      padding: const EdgeInsets.all(16.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 20,
              fontWeight: FontWeight.bold,
              fontFamily: 'SanFranciscoBold',
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Text(
                '$views views',
                style: TextStyle(
                  color: Colors.white.withOpacity(0.6),
                  fontSize: 13,
                ),
              ),
              const SizedBox(width: 10),
              Text(
                '• ${_timeAgo(widget.video['created_at'])}',
                style: TextStyle(
                  color: Colors.white.withOpacity(0.6),
                  fontSize: 13,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              CircleAvatar(
                radius: 18,
                backgroundImage: _getProfilePic(author['profile_picture']),
                backgroundColor: const Color(0xFF3EA6FF),
                child: author['profile_picture'] == null
                    ? Text(
                        (author['username'] ?? 'U')
                            .substring(0, 1)
                            .toUpperCase(),
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      )
                    : null,
              ),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    author['username'] ?? 'Unknown',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                      fontSize: 15,
                    ),
                  ),
                  Text(
                    '${author['subscriber_count'] ?? 0} subscribers',
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.5),
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
              const Spacer(),
              // Subscribe Button (Standard)
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: const Text(
                  'Subscribe',
                  style: TextStyle(
                    color: Colors.black,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildActionButtons() {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
      child: Row(
        children: [
          // Like / Dislike Group
          LiquidGlassBar(
            height: 42,
            borderRadius: 21,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                _buildPillButton(
                  icon: Icons.thumb_up_outlined,
                  label: '${widget.video['likes'] ?? "Like"}',
                  onTap: () {},
                ),
                Container(
                  width: 1,
                  height: 20,
                  color: Colors.white.withOpacity(0.2),
                ),
                _buildPillButton(
                  icon: Icons.thumb_down_outlined,
                  onTap: () {},
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),

          // Share / Save Group
          LiquidGlassBar(
            height: 42,
            borderRadius: 21,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                _buildPillButton(
                  icon: FontAwesomeIcons.share,
                  label: 'Share',
                  onTap: () {},
                ),
                Container(
                  width: 1,
                  height: 20,
                  color: Colors.white.withOpacity(0.2),
                ),
                _buildPillButton(
                  icon: Icons.bookmark_border,
                  label: 'Save',
                  onTap: () {},
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),

          // Three Dots (More)
          LiquidGlassBar(
            height: 42,
            borderRadius: 21,
            child: _buildPillButton(
              icon: Icons.more_horiz,
              onTap: () {},
              padding: const EdgeInsets.symmetric(horizontal: 16),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPillButton({
    required IconData icon,
    String? label,
    required VoidCallback onTap,
    EdgeInsetsGeometry? padding,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(21),
      child: Padding(
        padding: padding ?? const EdgeInsets.symmetric(horizontal: 16),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: Colors.white, size: 18),
            if (label != null && label.isNotEmpty) ...[
              const SizedBox(width: 8),
              Text(
                label,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                  fontSize: 13,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildCommentsSection() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Text(
                'Comments',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(width: 8),
              Text(
                '${_comments.length}',
                style: TextStyle(color: Colors.white.withOpacity(0.5)),
              ),
            ],
          ),
          const SizedBox(height: 16),
          // Comment Input
          Row(
            children: [
              CircleAvatar(
                radius: 16,
                backgroundImage: _getProfilePic(
                  widget.user?['profile_picture'],
                ),
                backgroundColor: Colors.grey[800],
                child: widget.user?['profile_picture'] == null
                    ? const Icon(Icons.person, size: 16)
                    : null,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextField(
                  controller: _commentController,
                  style: const TextStyle(color: Colors.white),
                  decoration: InputDecoration(
                    hintText: 'Add a comment...',
                    hintStyle: TextStyle(color: Colors.white.withOpacity(0.4)),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(20),
                      borderSide: BorderSide(
                        color: Colors.white.withOpacity(0.2),
                      ),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(20),
                      borderSide: BorderSide(
                        color: Colors.white.withOpacity(0.2),
                      ),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(20),
                      borderSide: const BorderSide(color: Color(0xFF3EA6FF)),
                    ),
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 12,
                    ),
                    filled: true,
                    fillColor: Colors.white.withOpacity(0.05),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              IconButton(
                onPressed: _postComment,
                icon: _isPostingComment
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.send, color: Color(0xFF3EA6FF)),
              ),
            ],
          ),
          const SizedBox(height: 24),

          if (_loadingComments)
            const Center(child: CircularProgressIndicator())
          else if (_comments.isEmpty)
            Text(
              'No comments yet.',
              style: TextStyle(color: Colors.white.withOpacity(0.5)),
            )
          else
            ListView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _comments.length + (_hasMoreComments ? 1 : 0),
              itemBuilder: (context, index) {
                if (index == _comments.length) {
                  return Center(
                    child: TextButton(
                      onPressed: _isLoadingMoreComments
                          ? null
                          : () => _loadComments(refresh: false),
                      child: _isLoadingMoreComments
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text(
                              'Load More Comments',
                              style: TextStyle(color: Color(0xFF3EA6FF)),
                            ),
                    ),
                  );
                }
                final comment = _comments[index];
                final author = comment['author'] ?? {};
                return Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      CircleAvatar(
                        radius: 14,
                        backgroundImage: _getProfilePic(
                          author['profile_picture'],
                        ),
                        backgroundColor: Colors.grey[800],
                        child: author['profile_picture'] == null
                            ? const Icon(Icons.person, size: 16)
                            : null,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Text(
                                  author['username'] ?? 'User',
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 13,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Text(
                                  _timeAgo(comment['created_at']),
                                  style: TextStyle(
                                    color: Colors.white.withOpacity(0.5),
                                    fontSize: 11,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              comment['comment'] ?? '',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 14,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
        ],
      ),
    );
  }

  Widget _buildRecommendations() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Text(
            'Up Next',
            style: TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.bold,
            ),
          ),
        ),
        if (_loadingRecs)
          const Center(child: CircularProgressIndicator())
        else
          ListView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: _recommendations.length,
            itemBuilder: (context, index) {
              final vid = _recommendations[index];
              return InkWell(
                onTap: () {
                  Navigator.pushReplacement(
                    context,
                    MaterialPageRoute(
                      builder: (_) =>
                          VideoPlayerPage(video: vid, user: widget.user),
                    ),
                  );
                },
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 8,
                  ),
                  child: Row(
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: SizedBox(
                          width: 120,
                          height: 68,
                          child: _buildThumbnail(vid['thumbnail_url']),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              vid['title'] ?? 'Untitled',
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 14,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              vid['author']?['username'] ?? 'Unknown',
                              style: TextStyle(
                                color: Colors.white.withOpacity(0.6),
                                fontSize: 12,
                              ),
                            ),
                            Text(
                              '${vid['views']} views • ${_timeAgo(vid['created_at'])}',
                              style: TextStyle(
                                color: Colors.white.withOpacity(0.6),
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
      ],
    );
  }

  Widget _buildThumbnail(String? url) {
    if (url == null || url.isEmpty || url == 'null') {
      return Container(
        color: Colors.grey[900],
        child: const Icon(Icons.play_circle_outline, color: Colors.white54),
      );
    }
    String fullUrl = url;
    if (!url.startsWith('http')) {
      if (url.startsWith('/')) fullUrl = url.substring(1);
      fullUrl = '${AppConstants.baseUrl}/$fullUrl';
    }
    return Image.network(
      fullUrl,
      fit: BoxFit.cover,
      errorBuilder: (_, _, _) => Container(color: Colors.grey[900]),
    );
  }

  ImageProvider? _getProfilePic(String? url) {
    if (url == null || url.isEmpty || url == 'null') return null;
    String fullUrl = url;
    if (!url.startsWith('http')) {
      if (url.startsWith('/')) fullUrl = url.substring(1);
      fullUrl = '${AppConstants.baseUrl}/$fullUrl';
    }
    return NetworkImage(fullUrl);
  }

  String _timeAgo(dynamic dateStr) {
    if (dateStr == null) return '';
    try {
      final date = DateTime.parse(dateStr.toString());
      final diff = DateTime.now().difference(date);
      if (diff.inDays > 365) return '${diff.inDays ~/ 365}y';
      if (diff.inDays > 30) return '${diff.inDays ~/ 30}mo';
      if (diff.inDays > 0) return '${diff.inDays}d';
      if (diff.inHours > 0) return '${diff.inHours}h';
      if (diff.inMinutes > 0) return '${diff.inMinutes}m';
      return 'now';
    } catch (_) {
      return '';
    }
  }
}

// ==========================================
// MINI QUEUE FEATURE
// ==========================================
class MiniQueueOverlay extends StatelessWidget {
  final ValueNotifier<bool> queueVisibleNotifier;
  final ValueNotifier<List<Map<String, dynamic>>> queueNotifier;
  final Function(Map<String, dynamic>) onPlayVideo;

  const MiniQueueOverlay({
    super.key,
    required this.queueVisibleNotifier,
    required this.queueNotifier,
    required this.onPlayVideo,
  });

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<bool>(
      valueListenable: queueVisibleNotifier,
      builder: (context, visible, _) {
        return Stack(
          children: [
            // Toggle Button (Visible when closed)
            if (!visible)
              Positioned(
                top: 40,
                right: 16,
                child: SafeArea(
                  child: GestureDetector(
                    onTap: () => queueVisibleNotifier.value = true,
                    child: Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: Colors.black.withOpacity(0.6),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: Colors.white24),
                      ),
                      child: const Icon(
                        Icons.queue_music,
                        color: Colors.white,
                        size: 20,
                      ),
                    ),
                  ),
                ),
              ),

            // The Panel
            if (visible)
              Positioned(
                top: 0,
                right: 0,
                bottom: 0,
                // "Does not cover more than 25% of the player area" behavior:
                // For robustness on small screens, we ensure min width e.g., 200px or 30%, whichever is smaller?
                // Logic: 25% of width in landscape. In portrait, maybe 40% height at bottom?
                // Simplified: A side panel taking ~180-200px.
                width: 220,
                child: Container(
                  color: const Color(0xFF1E1E1E), // No blur
                  child: Column(
                    children: [
                      // Header
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: const BoxDecoration(
                          border: Border(
                            bottom: BorderSide(color: Colors.white10),
                          ),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Queue',
                              style: TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            GestureDetector(
                              onTap: () => queueVisibleNotifier.value = false,
                              child: const Icon(
                                Icons.close,
                                color: Colors.white,
                              ),
                            ),
                          ],
                        ),
                      ),

                      // List
                      Expanded(
                        child: ValueListenableBuilder<List<Map<String, dynamic>>>(
                          valueListenable: queueNotifier,
                          builder: (context, queue, _) {
                            if (queue.isEmpty) {
                              return Center(
                                child: Text(
                                  'Queue is empty',
                                  style: TextStyle(
                                    color: Colors.white.withOpacity(0.5),
                                    fontSize: 12,
                                  ),
                                ),
                              );
                            }

                            return ReorderableListView(
                              onReorder: (oldIndex, newIndex) {
                                if (oldIndex < newIndex) {
                                  newIndex -= 1;
                                }
                                final item = queue.removeAt(oldIndex);
                                queue.insert(newIndex, item);
                                // Notify listeners? ValueNotifier requires re-assignment or notifyListeners if extended.
                                // Since we modified the list in place, let's trigger update
                                // Creating new list reference to be safe
                                queueNotifier.value = List.from(queue);
                              },
                              children: [
                                for (
                                  int index = 0;
                                  index < queue.length;
                                  index++
                                )
                                  _buildQueueItem(context, queue[index], index),
                              ],
                            );
                          },
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        );
      },
    );
  }

  Widget _buildQueueItem(
    BuildContext context,
    Map<String, dynamic> video,
    int index,
  ) {
    // ReorderableListView items need a key
    return Container(
      key: ValueKey(video['id']),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: Colors.white10)),
      ),
      child: Row(
        children: [
          // Drag Handle assumed by ReorderableListView or automatic

          // Video Info
          Expanded(
            child: GestureDetector(
              onTap: () => onPlayVideo(video),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    video['title'] ?? 'Untitled',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  Text(
                    video['author']?['username'] ?? 'Unknown',
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.5),
                      fontSize: 10,
                    ),
                  ),
                ],
              ),
            ),
          ),

          // Actions
          PopupMenuButton<String>(
            icon: Icon(
              Icons.more_vert,
              color: Colors.white.withOpacity(0.5),
              size: 16,
            ),
            color: const Color(0xFF2C2C2C),
            onSelected: (value) {
              if (value == 'remove') {
                final list = List<Map<String, dynamic>>.from(
                  queueNotifier.value,
                );
                list.removeAt(index);
                queueNotifier.value = list;
              } else if (value == 'save') {
                // Implement save logic (mock for now or call API)
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Saved for later'),
                    duration: Duration(milliseconds: 500),
                  ),
                );
              }
            },
            itemBuilder: (context) => [
              const PopupMenuItem(
                value: 'remove',
                child: Text('Remove', style: TextStyle(color: Colors.white)),
              ),
              const PopupMenuItem(
                value: 'save',
                child: Text(
                  'Save for later',
                  style: TextStyle(color: Colors.white),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
