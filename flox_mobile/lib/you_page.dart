import 'package:flutter/material.dart';
import 'dart:ui' as ui;

import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:image_picker/image_picker.dart';
import 'constants.dart';
import 'stars.dart';
import 'video_player_page.dart';
import 'widgets/native_liquid_glass.dart';
import 'user_list_page.dart';
import 'settings_page.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:share_plus/share_plus.dart';

/// Profile "You" page with gorgeous liquid glass aesthetic
class YouPage extends StatefulWidget {
  final Map<String, dynamic>? user;
  const YouPage({super.key, this.user});

  @override
  State<YouPage> createState() => _YouPageState();
}

class _YouPageState extends State<YouPage> with TickerProviderStateMixin {
  late AnimationController _bgController;
  late List<Star> _stars;

  Map<String, dynamic>? _profile;
  Map<String, dynamic>? _analytics;
  List<Map<String, dynamic>> _videos = [];
  bool _isLoading = true;
  String? _error;

  // Tab state
  final List<String> _tabs = ['Posts', 'Likes', 'Hearts', 'Saved', 'Reposts'];
  late PageController _tabPageController;

  // Custom Indicator State
  double _indicatorLeft = 0.0;
  double _tabWidth = 0.0;
  bool _isDragging = false;

  final ImagePicker _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    _stars = generateStars();
    _bgController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 30),
    )..repeat();

    _tabPageController = PageController(initialPage: 0);
    _tabPageController.addListener(() {
      if (!_isDragging && _tabPageController.hasClients && _tabWidth > 0) {
        setState(() {
          _indicatorLeft = (_tabPageController.page ?? 0) * _tabWidth;
        });
      }
    });

    _loadProfileData();
  }

  @override
  void dispose() {
    _bgController.dispose();
    _tabPageController.dispose();
    super.dispose();
  }

  Future<void> _loadProfileData() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final userId = widget.user?['id']?.toString() ?? '';
      if (userId.isEmpty) {
        setState(() {
          _error = 'Not logged in';
          _isLoading = false;
        });
        return;
      }

      // Fetch profile and analytics in parallel
      final responses = await Future.wait([
        http.get(
          Uri.parse(
            '${AppConstants.baseUrl}/backend/getUserProfile.php?user_id=$userId',
          ),
        ),
        http.get(
          Uri.parse(
            '${AppConstants.baseUrl}/backend/getUserProfileAnalytics.php',
          ),
        ),
      ]);

      final profileData = jsonDecode(responses[0].body);
      final analyticsData = jsonDecode(responses[1].body);

      if (profileData['success'] == true) {
        setState(() {
          _profile = profileData['user'];
          _videos = List<Map<String, dynamic>>.from(
            profileData['videos'] ?? [],
          );
          if (analyticsData['success'] == true) {
            _analytics = analyticsData;
          }
          _isLoading = false;
        });
      } else {
        setState(() {
          _error = profileData['message'] ?? 'Failed to load profile';
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = 'Connection Error: $e';
          _isLoading = false;
        });
      }
    }
  }

  void _onTabSelected(int index) {
    if (_tabWidth == 0) return;
    _tabPageController.animateToPage(
      index,
      duration: const Duration(milliseconds: 300),
      curve: Curves.easeOutCubic,
    );
  }

  void _snapToNearestTab() {
    if (_tabWidth == 0) return;

    // Calculate nearest tab based on current indicator position
    final double page = _indicatorLeft / _tabWidth;
    final int nearestTab = page.round().clamp(0, _tabs.length - 1);

    setState(() {
      _isDragging = false;
    });

    // Sync PageView to current drag position before animating
    // This prevents jump from 0.x -> 0 -> 1
    if (_tabPageController.hasClients) {
      final screenWidth = MediaQuery.of(context).size.width;
      // Approximation: assuming PageView fills width
      _tabPageController.jumpTo(page * screenWidth);
    }

    _tabPageController.animateToPage(
      nearestTab,
      duration: const Duration(milliseconds: 300),
      curve: Curves.easeOutCubic,
    );
  }

  void _openSettings() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => SettingsPage(user: widget.user)),
    );
  }

  Future<void> _pickImage() async {
    try {
      final XFile? image = await _picker.pickImage(source: ImageSource.gallery);

      if (image != null) {
        _uploadProfilePicture(image);
      }
    } catch (e) {
      print('Error picking image: $e');
    }
  }

  Future<void> _uploadProfilePicture(XFile image) async {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Uploading photo...'),
        backgroundColor: Color(0xFF1A1A25),
      ),
    );

    try {
      final uri = Uri.parse(
        '${AppConstants.baseUrl}/backend/uploadProfilePicture.php',
      );
      final request = http.MultipartRequest('POST', uri);

      request.files.add(
        await http.MultipartFile.fromPath('profile_picture', image.path),
      );

      // Add user_id for auth fallback
      final userId = widget.user?['id']?.toString() ?? '';
      if (userId.isNotEmpty) {
        request.fields['user_id'] = userId;
      }

      final streamedResponse = await request.send();
      final response = await http.Response.fromStream(streamedResponse);

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true) {
          _loadProfileData();
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Profile picture updated!')),
          );
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(data['message'] ?? 'Upload failed')),
          );
        }
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Server error uploading image')),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Error: $e')));
    }
  }

  void _showEditProfileSheet() {
    final usernameController = TextEditingController(
      text: _profile?['username'] ?? '',
    );
    final bioController = TextEditingController(text: _profile?['bio'] ?? '');

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
        ),
        child: Container(
          height: MediaQuery.of(context).size.height * 0.85,
          decoration: const BoxDecoration(
            color: Color(0xFF0A0A0F),
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            children: [
              // Handle bar
              Container(
                margin: const EdgeInsets.only(top: 12),
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.2),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              // Header
              Padding(
                padding: const EdgeInsets.all(20),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    GestureDetector(
                      onTap: () => Navigator.pop(context),
                      child: Text(
                        'Cancel',
                        style: TextStyle(
                          color: Colors.white.withOpacity(0.6),
                          fontSize: 16,
                        ),
                      ),
                    ),
                    const Text(
                      'Edit Profile',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    GestureDetector(
                      onTap: () => _saveProfile(
                        usernameController.text,
                        bioController.text,
                      ),
                      child: const Text(
                        'Save',
                        style: TextStyle(
                          color: Color(0xFF0071E3),
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Divider(color: Colors.white.withOpacity(0.1), height: 1),
              Expanded(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Profile Picture Section
                      Center(
                        child: Column(
                          children: [
                            _buildEditableAvatar(),
                            const SizedBox(height: 12),
                            GestureDetector(
                              onTap: _pickImage,
                              child: const Text(
                                'Change Photo',
                                style: TextStyle(
                                  color: Color(0xFF0071E3),
                                  fontSize: 14,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 32),
                      // Username field
                      _buildEditField(
                        label: 'Username',
                        controller: usernameController,
                        hint: 'Enter your username',
                      ),
                      const SizedBox(height: 24),
                      // Bio field
                      _buildEditField(
                        label: 'Bio',
                        controller: bioController,
                        hint: 'Write something about yourself...',
                        maxLines: 4,
                        maxLength: 150,
                      ),
                      // Extra space for keyboard
                      const SizedBox(height: 40),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _saveProfile(String username, String bio) async {
    Navigator.pop(context);
    try {
      final userId = widget.user?['id']?.toString() ?? '';
      final response = await http.post(
        Uri.parse('${AppConstants.baseUrl}/backend/updateProfile.php'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'user_id': userId, 'username': username, 'bio': bio}),
      );
      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        _loadProfileData();
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(const SnackBar(content: Text('Profile updated!')));
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(SnackBar(content: Text(data['message'] ?? 'Failed')));
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Error: $e')));
      }
    }
  }

  void _showShareProfilePopup() {
    final username = _profile?['username'] ?? 'Unknown';
    final userId = widget.user?['id']?.toString() ?? '';
    final profileUrl =
        '${AppConstants.baseUrl}/frontend/user_profile.php?user_id=$userId';

    showDialog(
      context: context,
      barrierColor: Colors.black.withOpacity(0.8),
      builder: (context) => Center(
        child: Material(
          color: Colors.transparent,
          child: Container(
            width: 320,
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: const Color(0xFF1A1A25).withOpacity(0.9),
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: Colors.white.withOpacity(0.1)),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.5),
                  blurRadius: 30,
                  spreadRadius: 10,
                ),
              ],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text(
                  'Share Profile',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  '@$username',
                  style: TextStyle(
                    fontSize: 14,
                    color: Colors.white.withOpacity(0.6),
                  ),
                ),
                const SizedBox(height: 24),
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: QrImageView(
                    data: profileUrl,
                    version: QrVersions.auto,
                    size: 200,
                    backgroundColor: Colors.white,
                  ),
                ),
                const SizedBox(height: 24),
                GestureDetector(
                  onTap: () {
                    Share.share(
                      'Check out my profile on FloxWatch: $profileUrl',
                    );
                    Navigator.pop(context);
                  },
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    decoration: BoxDecoration(
                      color: const Color(0xFF0071E3),
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF0071E3).withOpacity(0.3),
                          blurRadius: 12,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.share, color: Colors.white, size: 20),
                        SizedBox(width: 8),
                        Text(
                          'Share Link',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                            color: Colors.white,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                GestureDetector(
                  onTap: () => Navigator.pop(context),
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      vertical: 12,
                      horizontal: 24,
                    ),
                    child: Text(
                      'Close',
                      style: TextStyle(
                        fontSize: 16,
                        color: Colors.white.withOpacity(0.6),
                        fontWeight: FontWeight.w500,
                      ),
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

  Widget _buildEditableAvatar() {
    final profilePic = _profile?['profile_picture'];
    final username = _profile?['username'] ?? 'U';

    return GestureDetector(
      onTap: _pickImage,
      child: Stack(
        children: [
          Container(
            width: 100,
            height: 100,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(
                color: Colors.white.withOpacity(0.2),
                width: 3,
              ),
            ),
            child: ClipOval(
              child: profilePic != null && profilePic.isNotEmpty
                  ? Image.network(
                      AppConstants.sanitizeUrl(profilePic),
                      fit: BoxFit.cover,
                      errorBuilder: (_, _, _) => _buildDefaultAvatar(username),
                    )
                  : _buildDefaultAvatar(username),
            ),
          ),
          Positioned(
            bottom: 0,
            right: 0,
            child: Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFF0071E3),
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0xFF0A0A0F), width: 3),
              ),
              child: const Icon(
                Icons.camera_alt,
                size: 16,
                color: Colors.white,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEditField({
    required String label,
    required TextEditingController controller,
    required String hint,
    int maxLines = 1,
    int? maxLength,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: Colors.white.withOpacity(0.5),
            letterSpacing: 0.5,
          ),
        ),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(
            color: Colors.white.withOpacity(0.05),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: Colors.white.withOpacity(0.1)),
          ),
          child: TextField(
            controller: controller,
            maxLines: maxLines,
            maxLength: maxLength,
            style: const TextStyle(color: Colors.white, fontSize: 16),
            decoration: InputDecoration(
              hintText: hint,
              hintStyle: TextStyle(color: Colors.white.withOpacity(0.3)),
              border: InputBorder.none,
              contentPadding: const EdgeInsets.all(16),
            ),
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        _buildSpaceBackground(),
        _isLoading
            ? _buildLoadingState()
            : _error != null
            ? _buildErrorState()
            : _buildProfileContent(),
      ],
    );
  }

  Widget _buildSpaceBackground() {
    return RepaintBoundary(
      child: AnimatedBuilder(
        animation: _bgController,
        builder: (_, _) => CustomPaint(
          painter: NebulaPainter(_bgController.value, _stars),
          size: Size.infinite,
        ),
      ),
    );
  }

  Widget _buildLoadingState() {
    return Center(
      child: CircularProgressIndicator(
        strokeWidth: 3,
        valueColor: AlwaysStoppedAnimation(
          const Color(0xFF0071E3).withOpacity(0.8),
        ),
      ),
    );
  }

  Widget _buildErrorState() {
    return Center(
      child: Text(
        _error ?? 'Error',
        style: const TextStyle(color: Colors.white),
      ),
    );
  }

  Widget _buildProfileContent() {
    return RefreshIndicator(
      onRefresh: _loadProfileData,
      color: const Color(0xFF0071E3),
      backgroundColor: const Color(0xFF1A1A25),
      child: CustomScrollView(
        physics: const BouncingScrollPhysics(),
        slivers: [
          SliverToBoxAdapter(child: _buildProfileHeader()),
          SliverToBoxAdapter(child: _buildStatsRow()),
          SliverToBoxAdapter(child: _buildContentTabs()),
          SliverFillRemaining(child: _buildTabPageView()),
        ],
      ),
    );
  }

  Widget _buildProfileHeader() {
    final username = _profile?['username'] ?? 'Unknown';
    final bio = _profile?['bio'] ?? '';
    final profilePic = _profile?['profile_picture'];
    final isPro = _profile?['is_pro'] == true;

    return Container(
      padding: EdgeInsets.only(
        top: MediaQuery.of(context).padding.top + 20,
        left: 20,
        right: 20,
        bottom: 24,
      ),
      child: Column(
        children: [
          Stack(
            alignment: Alignment.center,
            children: [
              Container(
                width: 100,
                height: 100,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: Colors.white.withOpacity(0.2),
                    width: 3,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF0071E3).withOpacity(0.2),
                      blurRadius: 20,
                    ),
                  ],
                ),
                child: ClipOval(
                  child: profilePic != null && profilePic.isNotEmpty
                      ? Image.network(
                          AppConstants.sanitizeUrl(profilePic),
                          fit: BoxFit.cover,
                          errorBuilder: (_, _, _) =>
                              _buildDefaultAvatar(username),
                        )
                      : _buildDefaultAvatar(username),
                ),
              ),
              if (isPro)
                Positioned(
                  bottom: 0,
                  right: 0,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFFFFD700), Color(0xFFFFA500)],
                      ),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Text(
                      'PRO',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w900,
                        color: Colors.black,
                      ),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            '@$username',
            style: const TextStyle(
              fontSize: 26,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.5,
            ),
          ),
          if (bio.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              bio,
              textAlign: TextAlign.center,
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 14,
                color: Colors.white.withOpacity(0.6),
                height: 1.4,
              ),
            ),
          ],
          const SizedBox(height: 20),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              _buildGlassButton('Edit Profile', _showEditProfileSheet),
              const SizedBox(width: 12),
              _buildGlassIconButton(FontAwesomeIcons.gear, _openSettings),
              const SizedBox(width: 12),
              _buildGlassIconButton(
                FontAwesomeIcons.share,
                _showShareProfilePopup,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStatsRow() {
    final followers = _profile?['subscriber_count'] ?? 0;
    final following = _profile?['following_count'] ?? 0;
    final likes =
        _analytics?['likes']?.fold<int>(
          0,
          (sum, item) => sum + (item['count'] as int? ?? 0),
        ) ??
        0;

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      child: NativeLiquidGlassBar(
        height: 90,
        borderRadius: 24,
        child: ClipRRect(
          borderRadius: BorderRadius.circular(24),
          child: BackdropFilter(
            filter: ui.ImageFilter.blur(sigmaX: 15, sigmaY: 15),
            child: Container(
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  GestureDetector(
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => UserListPage(
                            userId: widget.user?['id'],
                            username: widget.user?['username'] ?? '',
                            initialTabIndex: 0,
                          ),
                        ),
                      );
                    },
                    child: _buildStatItem('$followers', 'Followers'),
                  ),
                  _buildStatDivider(),
                  GestureDetector(
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => UserListPage(
                            userId: widget.user?['id'],
                            username: widget.user?['username'] ?? '',
                            initialTabIndex: 1,
                          ),
                        ),
                      );
                    },
                    child: _buildStatItem('$following', 'Following'),
                  ),
                  _buildStatDivider(),
                  _buildStatItem('$likes', 'Likes'),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildStatItem(String value, String label) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          _formatNumber(int.tryParse(value) ?? 0),
          style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 4),
        Text(
          label,
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w500,
            color: Colors.white.withOpacity(0.5),
          ),
        ),
      ],
    );
  }

  Widget _buildStatDivider() {
    return Container(
      width: 1,
      height: 30,
      color: Colors.white.withOpacity(0.1),
    );
  }

  Widget _buildContentTabs() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
      child: NativeLiquidGlassBar(
        height: 50,
        borderRadius: 20,
        child: ClipRRect(
          borderRadius: BorderRadius.circular(20),
          child: BackdropFilter(
            filter: ui.ImageFilter.blur(sigmaX: 10, sigmaY: 10),
            child: LayoutBuilder(
              builder: (context, constraints) {
                _tabWidth = constraints.maxWidth / _tabs.length;

                // Recalculate indicator position on layout
                if (!_isDragging && _tabPageController.hasClients) {
                  _indicatorLeft = (_tabPageController.page ?? 0) * _tabWidth;
                }

                return Stack(
                  children: [
                    // Tabs Background Icons
                    Row(
                      children: List.generate(_tabs.length, (index) {
                        return Expanded(
                          child: GestureDetector(
                            onTap: () => _onTabSelected(index),
                            child: Container(
                              height: 50,
                              color: Colors.transparent,
                              child: Center(
                                child: FaIcon(
                                  _getTabIcon(index),
                                  size: 18,
                                  color: Colors.white.withOpacity(0.25),
                                ),
                              ),
                            ),
                          ),
                        );
                      }),
                    ),

                    // Draggable Indicator
                    Positioned(
                      left: _indicatorLeft,
                      top: 0,
                      bottom: 0,
                      width: _tabWidth,
                      child: GestureDetector(
                        onHorizontalDragStart: (details) {
                          setState(() {
                            _isDragging = true;
                          });
                        },
                        onHorizontalDragUpdate: (details) {
                          setState(() {
                            _indicatorLeft += details.delta.dx;
                            _indicatorLeft = _indicatorLeft.clamp(
                              0.0,
                              constraints.maxWidth - _tabWidth,
                            );
                          });

                          // Interactive sync
                          if (_tabPageController.hasClients && _tabWidth > 0) {
                            final maxIndicator =
                                constraints.maxWidth - _tabWidth;
                            if (maxIndicator > 0) {
                              final progress = _indicatorLeft / maxIndicator;
                              final maxScroll =
                                  _tabPageController.position.maxScrollExtent;
                              _tabPageController.jumpTo(progress * maxScroll);
                            }
                          }
                        },
                        onHorizontalDragEnd: (details) {
                          setState(() => _isDragging = false);
                          _snapToNearestTab();
                        },
                        child: AnimatedScale(
                          scale: _isDragging ? 1.15 : 1.0,
                          duration: const Duration(milliseconds: 150),
                          curve: Curves.easeOutCubic,
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 150),
                            margin: EdgeInsets.all(_isDragging ? 2 : 4),
                            decoration: BoxDecoration(
                              color: const Color(
                                0xFF0071E3,
                              ).withOpacity(_isDragging ? 0.35 : 0.2),
                              borderRadius: BorderRadius.circular(
                                _isDragging ? 18 : 16,
                              ),
                              boxShadow: _isDragging
                                  ? [
                                      BoxShadow(
                                        color: const Color(
                                          0xFF0071E3,
                                        ).withOpacity(0.4),
                                        blurRadius: 12,
                                      ),
                                    ]
                                  : null,
                            ),
                            child: Center(
                              child: AnimatedScale(
                                scale: _isDragging ? 1.2 : 1.1,
                                duration: const Duration(milliseconds: 150),
                                child: FaIcon(
                                  _getTabIcon(
                                    ((_indicatorLeft + _tabWidth / 2) /
                                            _tabWidth)
                                        .floor()
                                        .clamp(0, _tabs.length - 1),
                                  ),
                                  size: 18,
                                  color: const Color(0xFF0071E3),
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                );
              },
            ),
          ),
        ),
      ),
    );
  }

  IconData _getTabIcon(int index) {
    switch (index) {
      case 0:
        return FontAwesomeIcons.tableColumns;
      case 1:
        return FontAwesomeIcons.thumbsUp;
      case 2:
        return FontAwesomeIcons.heart;
      case 3:
        return FontAwesomeIcons.bookmark;
      case 4:
        return FontAwesomeIcons.retweet;
      default:
        return FontAwesomeIcons.tableColumns;
    }
  }

  Widget _buildTabPageView() {
    return PageView(
      controller: _tabPageController,

      children: [
        _buildPostsGrid(),
        _buildEmptyContentState('No likes yet'),
        _buildEmptyContentState('No hearts yet'),
        _buildEmptyContentState('No saved videos yet'),
        _buildEmptyContentState('No reposts yet'),
      ],
    );
  }

  Widget _buildPostsGrid() {
    if (_videos.isEmpty) {
      return _buildEmptyContentState('No posts yet');
    }
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: GridView.builder(
        padding: const EdgeInsets.only(bottom: 120),
        physics: const BouncingScrollPhysics(),
        shrinkWrap: false,
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 3,
          mainAxisSpacing: 2,
          crossAxisSpacing: 2,
          childAspectRatio: 9 / 16,
        ),
        itemCount: _videos.length,
        itemBuilder: (context, index) => _buildVideoThumbnail(_videos[index]),
      ),
    );
  }

  Widget _buildVideoThumbnail(Map<String, dynamic> video) {
    final thumbnail = video['thumbnail_url'];
    final views = video['views'] ?? 0;

    // Sanitize and handle video object
    // Pass 'user' object to video player page if not present in video map
    final videoWithUser = Map<String, dynamic>.from(video);
    if (!videoWithUser.containsKey('username')) {
      videoWithUser['username'] = _profile?['username'] ?? 'Unknown';
    }
    if (!videoWithUser.containsKey('user_id')) {
      videoWithUser['user_id'] = widget.user?['id'];
    }

    return GestureDetector(
      onTap: () {
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) =>
                VideoPlayerPage(video: videoWithUser, user: widget.user),
          ),
        );
      },
      child: Container(
        decoration: BoxDecoration(
          color: const Color(0xFF101015),
          border: Border.all(color: Colors.white.withOpacity(0.05)),
        ),
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (thumbnail != null && thumbnail.isNotEmpty)
              Image.network(
                AppConstants.sanitizeUrl(thumbnail),
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => _buildPlaceholderThumb(),
              )
            else
              _buildPlaceholderThumb(),
            Positioned(
              left: 4,
              bottom: 4,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.black.withOpacity(0.7),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.play_arrow,
                      size: 10,
                      color: Colors.white70,
                    ),
                    const SizedBox(width: 2),
                    Text(
                      _formatNumber(views),
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                        color: Colors.white70,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPlaceholderThumb() {
    return Container(
      color: const Color(0xFF1A1A25),
      child: Center(
        child: FaIcon(
          FontAwesomeIcons.play,
          size: 20,
          color: Colors.white.withOpacity(0.2),
        ),
      ),
    );
  }

  Widget _buildEmptyContentState(String message) {
    return Container(
      height: 200,
      alignment: Alignment.center,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          FaIcon(
            FontAwesomeIcons.folderOpen,
            size: 40,
            color: Colors.white.withOpacity(0.1),
          ),
          const SizedBox(height: 16),
          Text(
            message,
            style: TextStyle(
              color: Colors.white.withOpacity(0.4),
              fontSize: 14,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildGlassButton(String text, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.08),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.white.withOpacity(0.12)),
        ),
        child: Text(
          text,
          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
        ),
      ),
    );
  }

  Widget _buildGlassIconButton(IconData icon, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.08),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.white.withOpacity(0.12)),
        ),
        child: Center(
          child: FaIcon(icon, size: 16, color: Colors.white.withOpacity(0.8)),
        ),
      ),
    );
  }

  Widget _buildDefaultAvatar(String username) {
    return Container(
      color: const Color(0xFF1A1A25),
      child: Center(
        child: Text(
          username.isNotEmpty ? username.substring(0, 1).toUpperCase() : 'U',
          style: const TextStyle(
            fontSize: 40,
            fontWeight: FontWeight.w800,
            color: Color(0xFF0071E3),
          ),
        ),
      ),
    );
  }

  String _formatNumber(int number) {
    if (number >= 1000000) return '${(number / 1000000).toStringAsFixed(1)}M';
    if (number >= 1000) return '${(number / 1000).toStringAsFixed(1)}K';
    return number.toString();
  }
}
