import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'dart:ui' as ui;
import 'dart:convert';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:http/http.dart' as http;
import 'stars.dart';
import 'constants.dart';
import 'session_manager.dart';
import 'legal_text.dart';
import 'widgets/native_liquid_glass.dart';

/// Lightning effect painter - matches chat_detail_page exactly
class _TopLightningPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    if (size.width == 0 || size.height == 0) return;
    final rect = Offset.zero & size;
    // Calculate radius dynamically: cap it at half-height for perfect rounded ends
    final radius = size.height / 2;
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
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

/// Reusable Liquid Glass Back Button
class LiquidGlassBackButton extends StatelessWidget {
  const LiquidGlassBackButton({super.key});

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
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
    );
  }
}

/// Account Settings Page - styled like Chats page
class SettingsPage extends StatefulWidget {
  final Map<String, dynamic>? user;
  final VoidCallback? onLogout;

  const SettingsPage({super.key, this.user, this.onLogout});

  @override
  State<SettingsPage> createState() => _SettingsPageState();
}

class _SettingsPageState extends State<SettingsPage>
    with TickerProviderStateMixin {
  late AnimationController _bgController;
  late List<Star> _stars;

  bool _notificationsEnabled = true;
  bool _darkMode = true;
  bool _autoPlay = true;
  bool _dataSaver = false;
  bool _privateAccount = false;
  bool _showActivityStatus = true;
  bool _isLoading = false;
  int _selectedTab = 0;

  // Search
  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _stars = generateStars();
    _bgController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 20),
    )..repeat();
    _loadSettings();
  }

  @override
  void dispose() {
    _bgController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadSettings() async {
    // Load user preferences from backend
    try {
      final response = await http.get(
        Uri.parse('${AppConstants.baseUrl}/backend/getUser.php'),
      );
      final data = jsonDecode(response.body);
      if (data['success'] == true && data['user'] != null) {
        final user = data['user'];
        setState(() {
          _notificationsEnabled = user['email_notifications'] == 1;
          _privateAccount = user['profile_visibility'] == 'private';
        });
      }
    } catch (e) {
      debugPrint('Failed to load settings: $e');
    }
  }

  Future<void> _savePreference(String key, dynamic value) async {
    try {
      await http.post(
        Uri.parse(
          '${AppConstants.baseUrl}/backend/update_notification_prefs.php',
        ),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({key: value}),
      );
    } catch (e) {
      debugPrint('Failed to save preference: $e');
    }
  }

  Future<void> _logout() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: const Color(0xFF1A1A25),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Log Out', style: TextStyle(color: Colors.white)),
        content: const Text(
          'Are you sure you want to log out?',
          style: TextStyle(color: Colors.white70),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(
              'Cancel',
              style: TextStyle(color: Colors.white.withOpacity(0.6)),
            ),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text(
              'Log Out',
              style: TextStyle(color: Color(0xFFFF3B30)),
            ),
          ),
        ],
      ),
    );

    if (confirm == true) {
      setState(() => _isLoading = true);
      try {
        await SessionManager().post('/backend/logout.php', {});
        widget.onLogout?.call();
        if (mounted) {
          Navigator.of(context).popUntil((route) => route.isFirst);
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(SnackBar(content: Text('Logout failed: $e')));
        }
      } finally {
        if (mounted) setState(() => _isLoading = false);
      }
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
            // Animated nebula background
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
              child: _isLoading
                  ? const Center(
                      child: CircularProgressIndicator(
                        color: Color(0xFF007AFF),
                      ),
                    )
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _buildHeader(),
                        _buildTabBar(),
                        Expanded(child: _buildSettingsList()),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 20, 24, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Settings',
            style: TextStyle(
              fontSize: 40,
              fontWeight: FontWeight.w800,
              letterSpacing: -1.2,
              color: Colors.white,
              fontFamily: 'SanFranciscoExtrabold',
            ),
          ),
          const SizedBox(height: 16),
          Container(
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.06),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: Colors.white.withOpacity(0.08)),
            ),
            child: TextField(
              controller: _searchController,
              style: const TextStyle(color: Colors.white, fontSize: 16),
              decoration: InputDecoration(
                hintText: 'Search settings',
                hintStyle: TextStyle(
                  color: Colors.white.withOpacity(0.4),
                  fontSize: 16,
                ),
                prefixIcon: Icon(
                  Icons.search,
                  color: Colors.white.withOpacity(0.4),
                  size: 22,
                ),
                suffixIcon: _searchQuery.isNotEmpty
                    ? IconButton(
                        onPressed: () {
                          _searchController.clear();
                          setState(() => _searchQuery = '');
                        },
                        icon: Icon(
                          Icons.close,
                          color: Colors.white.withOpacity(0.4),
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
              onChanged: (value) =>
                  setState(() => _searchQuery = value.toLowerCase().trim()),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTabBar() {
    final tabs = ['Account', 'Preferences', 'Privacy'];
    return Container(
      height: 44,
      margin: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
      child: Row(
        children: List.generate(tabs.length, (index) {
          final isSelected = _selectedTab == index;
          return GestureDetector(
            onTap: () => setState(() => _selectedTab = index),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              margin: const EdgeInsets.only(right: 10),
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
              decoration: BoxDecoration(
                color: isSelected
                    ? const Color(0xFF007AFF)
                    : Colors.white.withOpacity(0.06),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                tabs[index],
                style: TextStyle(
                  color: isSelected
                      ? Colors.white
                      : Colors.white.withOpacity(0.5),
                  fontWeight: isSelected ? FontWeight.w600 : FontWeight.w500,
                  fontSize: 14,
                ),
              ),
            ),
          );
        }),
      ),
    );
  }

  Widget _buildSettingsList() {
    List<_SettingItem> items;

    switch (_selectedTab) {
      case 0: // Account
        items = [
          _SettingItem(
            icon: FontAwesomeIcons.user,
            title: 'Username',
            subtitle: '@${widget.user?['username'] ?? 'user'}',
            onTap: () => _navigateTo(ChangeUsernamePage(user: widget.user)),
          ),
          _SettingItem(
            icon: FontAwesomeIcons.envelope,
            title: 'Email',
            subtitle: widget.user?['email'] ?? 'Not set',
            onTap: () => _navigateTo(ChangeEmailPage(user: widget.user)),
          ),
          _SettingItem(
            icon: FontAwesomeIcons.lock,
            title: 'Change Password',
            subtitle: 'Update your password',
            onTap: () => _navigateTo(const ChangePasswordPage()),
          ),
          _SettingItem(
            icon: FontAwesomeIcons.shieldHalved,
            title: 'Two-Factor Authentication',
            subtitle: 'Add extra security',
            onTap: () => _navigateTo(const TwoFactorAuthPage()),
          ),
          _SettingItem(
            icon: FontAwesomeIcons.arrowRightFromBracket,
            title: 'Log Out',
            subtitle: 'Sign out of your account',
            iconColor: const Color(0xFFFF9500),
            onTap: _logout,
          ),
          _SettingItem(
            icon: FontAwesomeIcons.trash,
            title: 'Delete Account',
            subtitle: 'Permanently delete your account',
            iconColor: const Color(0xFFFF3B30),
            onTap: () => _navigateTo(const DeleteAccountPage()),
          ),
        ];
        break;
      case 1: // Preferences
        items = [
          _SettingItem(
            icon: FontAwesomeIcons.bell,
            title: 'Notifications',
            subtitle: _notificationsEnabled ? 'Enabled' : 'Disabled',
            trailing: _buildSwitch(_notificationsEnabled, (v) {
              setState(() => _notificationsEnabled = v);
              _savePreference('email_notifications', v);
            }),
          ),
          _SettingItem(
            icon: FontAwesomeIcons.play,
            title: 'Auto-Play Videos',
            subtitle: _autoPlay ? 'On' : 'Off',
            trailing: _buildSwitch(
              _autoPlay,
              (v) => setState(() => _autoPlay = v),
            ),
          ),
          _SettingItem(
            icon: FontAwesomeIcons.wifi,
            title: 'Data Saver',
            subtitle: _dataSaver ? 'Reduce data usage' : 'Off',
            trailing: _buildSwitch(
              _dataSaver,
              (v) => setState(() => _dataSaver = v),
            ),
          ),
          _SettingItem(
            icon: FontAwesomeIcons.moon,
            title: 'Dark Mode',
            subtitle: 'Always on',
            trailing: _buildSwitch(
              _darkMode,
              (v) => setState(() => _darkMode = v),
            ),
          ),
          _SettingItem(
            icon: FontAwesomeIcons.book,
            title: 'Help Center',
            subtitle: 'Get help and support',
            onTap: () => _navigateTo(const HelpCenterPage()),
          ),
          _SettingItem(
            icon: FontAwesomeIcons.circleInfo,
            title: 'About Loop',
            subtitle: 'Version 1.0.0',
            onTap: () {},
          ),
          _SettingItem(
            icon: FontAwesomeIcons.message,
            title: 'Send Feedback',
            subtitle: 'Tell us what you think',
            onTap: () => _navigateTo(const HelpCenterPage(isFeedback: true)),
          ),
        ];
        break;
      case 2: // Privacy
        items = [
          _SettingItem(
            icon: FontAwesomeIcons.eyeSlash,
            title: 'Private Account',
            subtitle: _privateAccount
                ? 'Only followers see your content'
                : 'Public',
            trailing: _buildSwitch(_privateAccount, (v) {
              setState(() => _privateAccount = v);
              _savePreference('profile_visibility', v ? 'private' : 'public');
            }),
          ),
          _SettingItem(
            icon: FontAwesomeIcons.circleCheck,
            title: 'Activity Status',
            subtitle: _showActivityStatus
                ? 'Others can see when you\'re active'
                : 'Hidden',
            trailing: _buildSwitch(
              _showActivityStatus,
              (v) => setState(() => _showActivityStatus = v),
            ),
          ),
          _SettingItem(
            icon: FontAwesomeIcons.ban,
            title: 'Blocked Accounts',
            subtitle: 'Manage blocked users',
            onTap: () => _navigateTo(const BlockedAccountsPage()),
          ),
          _SettingItem(
            icon: FontAwesomeIcons.download,
            title: 'Download Your Data',
            subtitle: 'Request a copy of your data',
            onTap: () => _navigateTo(const DownloadDataPage()),
          ),
          _SettingItem(
            icon: FontAwesomeIcons.fileContract,
            title: 'Terms of Service',
            subtitle: 'Read our terms',
            onTap: () => _navigateTo(const TermsOfServicePage()),
          ),
          _SettingItem(
            icon: FontAwesomeIcons.userSecret,
            title: 'Privacy Policy',
            subtitle: 'How we handle your data',
            onTap: () => _navigateTo(const PrivacyPolicyPage()),
          ),
        ];
        break;
      default:
        items = [];
    }

    // Filter by search
    if (_searchQuery.isNotEmpty) {
      items = items.where((item) {
        return item.title.toLowerCase().contains(_searchQuery) ||
            item.subtitle.toLowerCase().contains(_searchQuery);
      }).toList();
    }

    if (items.isEmpty) {
      return _buildEmptyState();
    }

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
      physics: const BouncingScrollPhysics(),
      itemCount: items.length,
      itemBuilder: (context, index) => _buildSettingTile(items[index]),
    );
  }

  Widget _buildSettingTile(_SettingItem item) {
    return GestureDetector(
      onTap: item.onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.04),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: (item.iconColor ?? const Color(0xFF007AFF)).withOpacity(
                  0.15,
                ),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Center(
                child: FaIcon(
                  item.icon,
                  size: 18,
                  color: item.iconColor ?? const Color(0xFF007AFF),
                ),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.title,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    item.subtitle,
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.4),
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
            ),
            if (item.trailing != null)
              item.trailing!
            else if (item.onTap != null)
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

  Widget _buildSwitch(bool value, ValueChanged<bool> onChanged) {
    return Switch(
      value: value,
      onChanged: onChanged,
      activeThumbColor: const Color(0xFF007AFF),
      activeTrackColor: const Color(0xFF007AFF).withOpacity(0.3),
      inactiveThumbColor: Colors.white.withOpacity(0.5),
      inactiveTrackColor: Colors.white.withOpacity(0.1),
    );
  }

  Widget _buildEmptyState() {
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
            'No settings found',
            style: TextStyle(
              color: Colors.white.withOpacity(0.5),
              fontSize: 17,
              fontWeight: FontWeight.bold,
              fontFamily: 'SanFranciscoExtrabold',
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

  void _navigateTo(Widget page) {
    Navigator.push(context, MaterialPageRoute(builder: (_) => page));
  }
}

class _SettingItem {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback? onTap;
  final Widget? trailing;
  final Color? iconColor;

  _SettingItem({
    required this.icon,
    required this.title,
    required this.subtitle,
    this.onTap,
    this.trailing,
    this.iconColor,
  });
}

// ============================================================================
// INDIVIDUAL SETTINGS PAGES - All with Chats-style design
// ============================================================================

Widget _buildSettingsSubPage({
  required BuildContext context,
  required String title,
  required Widget content,
  required AnimationController bgController,
  required List<Star> stars,
}) {
  return AnnotatedRegion<SystemUiOverlayStyle>(
    value: SystemUiOverlayStyle.light,
    child: Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Container(color: const Color(0xFF0A0A0F)),
          RepaintBoundary(
            child: AnimatedBuilder(
              animation: bgController,
              builder: (_, _) => CustomPaint(
                painter: NebulaPainter(bgController.value, stars),
                size: Size.infinite,
              ),
            ),
          ),
          SafeArea(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(24, 20, 24, 8),
                  child: Row(
                    children: [
                      const LiquidGlassBackButton(),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Text(
                          title,
                          style: const TextStyle(
                            fontSize: 32,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.8,
                            color: Colors.white,
                            fontFamily: 'SanFranciscoExtrabold',
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: SingleChildScrollView(
                    physics: const BouncingScrollPhysics(),
                    padding: const EdgeInsets.all(24),
                    child: content,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

// Change Username Page
class ChangeUsernamePage extends StatefulWidget {
  final Map<String, dynamic>? user;
  const ChangeUsernamePage({super.key, this.user});

  @override
  State<ChangeUsernamePage> createState() => _ChangeUsernamePageState();
}

class _ChangeUsernamePageState extends State<ChangeUsernamePage>
    with SingleTickerProviderStateMixin {
  late TextEditingController _controller;
  late AnimationController _bgController;
  late List<Star> _stars;
  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.user?['username'] ?? '');
    _stars = generateStars();
    _bgController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 20),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    _bgController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final newUsername = _controller.text.trim();
    if (newUsername.isEmpty) return;
    if (newUsername.length < 3 || newUsername.length > 24) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Username must be 3-24 characters'),
          backgroundColor: Color(0xFFFF3B30),
        ),
      );
      return;
    }

    setState(() => _isSaving = true);

    try {
      final response = await SessionManager().post('/backend/updateUser.php', {
        'username': newUsername,
      });

      final data = jsonDecode(response.body);

      if (mounted) {
        if (data['success'] == true) {
          Navigator.pop(context, data['user']);
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Username updated successfully'),
              backgroundColor: Color(0xFF34C759),
            ),
          );
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(data['message'] ?? 'Failed to update username'),
              backgroundColor: const Color(0xFFFF3B30),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $e'),
            backgroundColor: const Color(0xFFFF3B30),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _buildSettingsSubPage(
      context: context,
      title: 'Username',
      bgController: _bgController,
      stars: _stars,
      content: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Choose a unique username',
            style: TextStyle(
              color: Colors.white.withOpacity(0.6),
              fontSize: 14,
            ),
          ),
          const SizedBox(height: 16),
          Container(
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.06),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: Colors.white.withOpacity(0.08)),
            ),
            child: TextField(
              controller: _controller,
              style: const TextStyle(color: Colors.white, fontSize: 18),
              decoration: InputDecoration(
                hintText: 'Username',
                hintStyle: TextStyle(color: Colors.white.withOpacity(0.3)),
                prefixText: '@',
                prefixStyle: TextStyle(
                  color: Colors.white.withOpacity(0.5),
                  fontSize: 18,
                ),
                border: InputBorder.none,
                contentPadding: const EdgeInsets.all(16),
              ),
            ),
          ),
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: _isSaving ? null : _save,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF007AFF),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              child: _isSaving
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2,
                      ),
                    )
                  : const Text(
                      'Save',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

// Change Email Page
class ChangeEmailPage extends StatefulWidget {
  final Map<String, dynamic>? user;
  const ChangeEmailPage({super.key, this.user});

  @override
  State<ChangeEmailPage> createState() => _ChangeEmailPageState();
}

class _ChangeEmailPageState extends State<ChangeEmailPage>
    with SingleTickerProviderStateMixin {
  late TextEditingController _controller;
  late AnimationController _bgController;
  late List<Star> _stars;
  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.user?['email'] ?? '');
    _stars = generateStars();
    _bgController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 20),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    _bgController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final newEmail = _controller.text.trim();
    if (newEmail.isEmpty) return;

    // Basic email validation
    final emailRegex = RegExp(r'^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$');
    if (!emailRegex.hasMatch(newEmail)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please enter a valid email address'),
          backgroundColor: Color(0xFFFF3B30),
        ),
      );
      return;
    }

    setState(() => _isSaving = true);

    try {
      final response = await SessionManager().post('/backend/updateUser.php', {
        'email': newEmail,
      });

      final data = jsonDecode(response.body);

      if (mounted) {
        if (data['success'] == true) {
          Navigator.pop(context, data['user']);
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Email updated successfully'),
              backgroundColor: Color(0xFF34C759),
            ),
          );
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(data['message'] ?? 'Failed to update email'),
              backgroundColor: const Color(0xFFFF3B30),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $e'),
            backgroundColor: const Color(0xFFFF3B30),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _buildSettingsSubPage(
      context: context,
      title: 'Email',
      bgController: _bgController,
      stars: _stars,
      content: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'We\'ll send a verification link to your new email',
            style: TextStyle(
              color: Colors.white.withOpacity(0.6),
              fontSize: 14,
            ),
          ),
          const SizedBox(height: 16),
          Container(
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.06),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: Colors.white.withOpacity(0.08)),
            ),
            child: TextField(
              controller: _controller,
              keyboardType: TextInputType.emailAddress,
              style: const TextStyle(color: Colors.white, fontSize: 18),
              decoration: InputDecoration(
                hintText: 'email@example.com',
                hintStyle: TextStyle(color: Colors.white.withOpacity(0.3)),
                border: InputBorder.none,
                contentPadding: const EdgeInsets.all(16),
              ),
            ),
          ),
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: _isSaving ? null : _save,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF007AFF),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              child: _isSaving
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2,
                      ),
                    )
                  : const Text(
                      'Send Verification',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

// Change Password Page
class ChangePasswordPage extends StatefulWidget {
  const ChangePasswordPage({super.key});

  @override
  State<ChangePasswordPage> createState() => _ChangePasswordPageState();
}

class _ChangePasswordPageState extends State<ChangePasswordPage>
    with SingleTickerProviderStateMixin {
  final _currentController = TextEditingController();
  final _newController = TextEditingController();
  final _confirmController = TextEditingController();
  late AnimationController _bgController;
  late List<Star> _stars;
  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    _stars = generateStars();
    _bgController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 20),
    )..repeat();
  }

  @override
  void dispose() {
    _currentController.dispose();
    _newController.dispose();
    _confirmController.dispose();
    _bgController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final currentPassword = _currentController.text;
    final newPassword = _newController.text;
    final confirmPassword = _confirmController.text;

    if (currentPassword.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please enter your current password'),
          backgroundColor: Color(0xFFFF3B30),
        ),
      );
      return;
    }

    if (newPassword.length < 6) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('New password must be at least 6 characters'),
          backgroundColor: Color(0xFFFF3B30),
        ),
      );
      return;
    }

    if (newPassword != confirmPassword) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Passwords don\'t match'),
          backgroundColor: Color(0xFFFF3B30),
        ),
      );
      return;
    }

    setState(() => _isSaving = true);

    try {
      final response = await SessionManager().post('/backend/updateUser.php', {
        'password': newPassword,
        'current_password': currentPassword,
      });

      final data = jsonDecode(response.body);

      if (mounted) {
        if (data['success'] == true) {
          Navigator.pop(context);
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Password updated successfully'),
              backgroundColor: Color(0xFF34C759),
            ),
          );
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(data['message'] ?? 'Failed to update password'),
              backgroundColor: const Color(0xFFFF3B30),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $e'),
            backgroundColor: const Color(0xFFFF3B30),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  Widget _buildPasswordField(TextEditingController controller, String hint) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.06),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.white.withOpacity(0.08)),
      ),
      child: TextField(
        controller: controller,
        obscureText: true,
        style: const TextStyle(color: Colors.white, fontSize: 16),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: TextStyle(color: Colors.white.withOpacity(0.3)),
          border: InputBorder.none,
          contentPadding: const EdgeInsets.all(16),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return _buildSettingsSubPage(
      context: context,
      title: 'Password',
      bgController: _bgController,
      stars: _stars,
      content: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildPasswordField(_currentController, 'Current Password'),
          _buildPasswordField(_newController, 'New Password'),
          _buildPasswordField(_confirmController, 'Confirm New Password'),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: _isSaving ? null : _save,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF007AFF),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              child: _isSaving
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2,
                      ),
                    )
                  : const Text(
                      'Update Password',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

// Simple placeholder pages with animated background
class _SimpleSettingsPage extends StatefulWidget {
  final String title;
  final String description;

  const _SimpleSettingsPage({required this.title, required this.description});

  @override
  State<_SimpleSettingsPage> createState() => _SimpleSettingsPageState();
}

class _SimpleSettingsPageState extends State<_SimpleSettingsPage>
    with SingleTickerProviderStateMixin {
  late AnimationController _bgController;
  late List<Star> _stars;

  @override
  void initState() {
    super.initState();
    _stars = generateStars();
    _bgController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 20),
    )..repeat();
  }

  @override
  void dispose() {
    _bgController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return _buildSettingsSubPage(
      context: context,
      title: widget.title,
      bgController: _bgController,
      stars: _stars,
      content: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            widget.description,
            style: const TextStyle(
              color: Colors.white, // Increased visibility from 0.6 opacity
              fontSize: 17, // Increased size from 15
              height: 1.6, // Improved line height for readability
            ),
          ),
        ],
      ),
    );
  }
}

class TwoFactorAuthPage extends StatefulWidget {
  const TwoFactorAuthPage({super.key});

  @override
  State<TwoFactorAuthPage> createState() => _TwoFactorAuthPageState();
}

class _TwoFactorAuthPageState extends State<TwoFactorAuthPage>
    with SingleTickerProviderStateMixin {
  late AnimationController _bgController;
  late List<Star> _stars;
  bool _isLoading = false;
  bool _isEnabled = false;

  @override
  void initState() {
    super.initState();
    _stars = generateStars();
    _bgController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 20),
    )..repeat();
    _checkStatus();
  }

  @override
  void dispose() {
    _bgController.dispose();
    super.dispose();
  }

  Future<void> _checkStatus() async {
    // Check initial status from getUser endpoint if available, or assume disabled until we fetch
    // For now, we'll fetch from getUser.php in a real app, but I'll skip that for brevity and just show the toggle
    // You might want to pass the current user state to this page
    try {
      final response = await http.get(
        Uri.parse('${AppConstants.baseUrl}/backend/getUser.php'),
      );
      final data = jsonDecode(response.body);
      if (data['success'] == true && data['user'] != null) {
        final val = data['user']['two_factor_enabled'];
        setState(() {
          _isEnabled = val == true || val == 1;
        });
      }
    } catch (e) {
      debugPrint('Error fetching 2FA status: $e');
    }
  }

  Future<void> _toggle2FA(bool value) async {
    setState(() => _isLoading = true);
    try {
      final response = await SessionManager().post('/backend/toggle_2fa.php', {
        'enabled': value ? 1 : 0,
      });
      final data = jsonDecode(response.body);

      if (mounted) {
        if (data['success'] == true) {
          setState(() => _isEnabled = value);
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                'Two-factor authentication ${value ? 'enabled' : 'disabled'}',
              ),
              backgroundColor: const Color(0xFF34C759),
            ),
          );
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(data['message'] ?? 'Failed to update 2FA'),
              backgroundColor: const Color(0xFFFF3B30),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $e'),
            backgroundColor: const Color(0xFFFF3B30),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _buildSettingsSubPage(
      context: context,
      title: '2FA',
      bgController: _bgController,
      stars: _stars,
      content: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.06),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.blue.withOpacity(0.2),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.security, color: Colors.blue),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Email Verification',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Receive a code via email when logging in from a new device.',
                        style: TextStyle(
                          color: Colors.white.withOpacity(0.6),
                          fontSize: 13,
                        ),
                      ),
                    ],
                  ),
                ),
                Switch(
                  value: _isEnabled,
                  onChanged: _isLoading ? null : _toggle2FA,
                  activeThumbColor: Colors.blue,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class BlockedAccountsPage extends StatefulWidget {
  const BlockedAccountsPage({super.key});

  @override
  State<BlockedAccountsPage> createState() => _BlockedAccountsPageState();
}

class _BlockedAccountsPageState extends State<BlockedAccountsPage>
    with SingleTickerProviderStateMixin {
  late AnimationController _bgController;
  late List<Star> _stars;
  List<dynamic> _blockedUsers = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _stars = generateStars();
    _bgController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 20),
    )..repeat();
    _fetchBlockedUsers();
  }

  @override
  void dispose() {
    _bgController.dispose();
    super.dispose();
  }

  Future<void> _fetchBlockedUsers() async {
    try {
      final response = await SessionManager().get(
        '/backend/getBlockedUsers.php',
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true) {
          setState(() {
            _blockedUsers = data['users'] ?? [];
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      debugPrint('Error fetching blocked users: $e');
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _unblockUser(int userId) async {
    try {
      final response = await SessionManager().post('/backend/unblockUser.php', {
        'blocked_id': userId,
      });
      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        setState(() {
          _blockedUsers.removeWhere((u) => u['id'] == userId);
        });
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('User unblocked'),
              backgroundColor: Color(0xFF34C759),
            ),
          );
        }
      }
    } catch (e) {
      debugPrint('Error unblocking user: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    return _buildSettingsSubPage(
      context: context,
      title: 'Blocked',
      bgController: _bgController,
      stars: _stars,
      content: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Colors.white))
          : _blockedUsers.isEmpty
          ? Center(
              child: Text(
                'You haven\'t blocked anyone yet.',
                style: TextStyle(
                  color: Colors.white.withOpacity(0.6),
                  fontSize: 16,
                ),
              ),
            )
          : ListView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _blockedUsers.length,
              itemBuilder: (context, index) {
                final user = _blockedUsers[index];
                return ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: CircleAvatar(
                    radius: 20,
                    backgroundColor: Colors.white.withOpacity(0.1),
                    backgroundImage: user['profile_picture'] != null
                        ? NetworkImage(
                            '${AppConstants.baseUrl}${user['profile_picture']}',
                          )
                        : null,
                    child: user['profile_picture'] == null
                        ? const Icon(Icons.person, color: Colors.white)
                        : null,
                  ),
                  title: Text(
                    user['username'] ?? 'Unknown',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  trailing: TextButton(
                    onPressed: () =>
                        _unblockUser(int.parse(user['id'].toString())),
                    child: const Text(
                      'Unblock',
                      style: TextStyle(color: Color(0xFFFF3B30)),
                    ),
                  ),
                );
              },
            ),
    );
  }
}

class DownloadDataPage extends StatefulWidget {
  const DownloadDataPage({super.key});

  @override
  State<DownloadDataPage> createState() => _DownloadDataPageState();
}

class _DownloadDataPageState extends State<DownloadDataPage>
    with SingleTickerProviderStateMixin {
  late AnimationController _bgController;
  late List<Star> _stars;
  bool _isRequesting = false;

  @override
  void initState() {
    super.initState();
    _stars = generateStars();
    _bgController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 20),
    )..repeat();
  }

  @override
  void dispose() {
    _bgController.dispose();
    super.dispose();
  }

  Future<void> _requestData() async {
    setState(() => _isRequesting = true);
    try {
      final response = await SessionManager().post(
        '/backend/requestDataDownload.php',
        {},
      );
      final data = jsonDecode(response.body);

      if (mounted) {
        if (data['success'] == true) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Request submitted. We\'ll email you when ready.'),
              backgroundColor: Color(0xFF34C759),
            ),
          );
          Navigator.pop(context);
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(data['message'] ?? 'Failed to request data'),
              backgroundColor: const Color(0xFFFF3B30),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $e'),
            backgroundColor: const Color(0xFFFF3B30),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isRequesting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _buildSettingsSubPage(
      context: context,
      title: 'Your Data',
      bgController: _bgController,
      stars: _stars,
      content: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Request a copy of all your data including videos, comments, and account information. This file will be sent to your email address.',
            style: TextStyle(
              color: Colors.white.withOpacity(0.6),
              fontSize: 15,
              height: 1.5,
            ),
          ),
          const SizedBox(height: 32),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: _isRequesting ? null : _requestData,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF007AFF),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              child: _isRequesting
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2,
                      ),
                    )
                  : const Text(
                      'Request Data Download',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

class HelpCenterPage extends StatefulWidget {
  final bool isFeedback;
  const HelpCenterPage({super.key, this.isFeedback = false});

  @override
  State<HelpCenterPage> createState() => _HelpCenterPageState();
}

class _HelpCenterPageState extends State<HelpCenterPage>
    with SingleTickerProviderStateMixin {
  late AnimationController _bgController;
  late List<Star> _stars;
  final TextEditingController _controller = TextEditingController();
  bool _isSending = false;

  @override
  void initState() {
    super.initState();
    _stars = generateStars();
    _bgController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 20),
    )..repeat();
  }

  @override
  void dispose() {
    _bgController.dispose();
    _controller.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final text = _controller.text.trim();
    if (text.isEmpty) return;

    setState(() => _isSending = true);

    try {
      final type = widget.isFeedback ? 'Feedback' : 'Help Question';
      final response = await SessionManager().post(
        '/backend/send_feedback.php',
        {'message': text, 'type': type},
      );

      final data = jsonDecode(response.body);

      if (mounted) {
        if (data['success'] == true) {
          _controller.clear();
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('$type sent! Thank you.'),
              backgroundColor: const Color(0xFF34C759),
            ),
          );
          Navigator.pop(context);
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(data['message'] ?? 'Failed to send'),
              backgroundColor: const Color(0xFFFF3B30),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $e'),
            backgroundColor: const Color(0xFFFF3B30),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _buildSettingsSubPage(
      context: context,
      title: widget.isFeedback ? 'Feedback' : 'Help',
      bgController: _bgController,
      stars: _stars,
      content: SizedBox(
        height: MediaQuery.of(context).size.height - 180, // Full height
        child: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                child: Text(
                  widget.isFeedback
                      ? 'We would love to hear your thoughts, suggestions, or concerns. Please describe them below.'
                      : 'For support, please email support@floxwatch.com or describe your issue below.\n\n'
                            'Common Questions:\n'
                            '• How to upload videos?\n'
                            '• How to change my password?\n'
                            '• Privacy settings explanation',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 17,
                    height: 1.6,
                  ),
                ),
              ),
            ),
            _buildInputBar(),
          ],
        ),
      ),
    );
  }

  Widget _buildInputBar() {
    return Container(
      margin: const EdgeInsets.only(top: 20),
      child: NativeLiquidGlassBar(
        height: 56,
        borderRadius: 24,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _controller,
                  style: const TextStyle(color: Colors.white, fontSize: 16),
                  cursorColor: const Color(0xFF007AFF),
                  decoration: InputDecoration(
                    hintText: widget.isFeedback
                        ? 'Write feedback...'
                        : 'Ask a question...',
                    hintStyle: TextStyle(
                      color: Colors.white.withOpacity(0.5),
                      fontSize: 16,
                    ),
                    border: InputBorder.none,
                    isDense: true,
                    contentPadding: EdgeInsets.zero,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              if (_isSending)
                const SizedBox(
                  width: 24,
                  height: 24,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    valueColor: AlwaysStoppedAnimation(Colors.white),
                  ),
                )
              else
                GestureDetector(
                  onTap: _send,
                  child: Container(
                    width: 32,
                    height: 32,
                    decoration: const BoxDecoration(
                      color: Color(0xFF007AFF),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.arrow_upward,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class TermsOfServicePage extends StatelessWidget {
  const TermsOfServicePage({super.key});
  @override
  Widget build(BuildContext context) =>
      const _SimpleSettingsPage(title: 'Terms', description: kTermsOfService);
}

class PrivacyPolicyPage extends StatelessWidget {
  const PrivacyPolicyPage({super.key});
  @override
  Widget build(BuildContext context) =>
      const _SimpleSettingsPage(title: 'Privacy', description: kPrivacyPolicy);
}

class DeleteAccountPage extends StatefulWidget {
  final VoidCallback? onDeleted;
  const DeleteAccountPage({super.key, this.onDeleted});

  @override
  State<DeleteAccountPage> createState() => _DeleteAccountPageState();
}

class _DeleteAccountPageState extends State<DeleteAccountPage>
    with SingleTickerProviderStateMixin {
  final _passwordController = TextEditingController();
  late AnimationController _bgController;
  late List<Star> _stars;
  bool _isDeleting = false;

  @override
  void initState() {
    super.initState();
    _stars = generateStars();
    _bgController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 20),
    )..repeat();
  }

  @override
  void dispose() {
    _passwordController.dispose();
    _bgController.dispose();
    super.dispose();
  }

  Future<void> _deleteAccount() async {
    if (_passwordController.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please enter your password'),
          backgroundColor: Color(0xFFFF3B30),
        ),
      );
      return;
    }

    // Show confirmation dialog
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: const Color(0xFF1A1A25),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text(
          'Delete Account?',
          style: TextStyle(color: Colors.white),
        ),
        content: const Text(
          'This action is PERMANENT and cannot be undone. All your data will be deleted forever.',
          style: TextStyle(color: Colors.white70),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text(
              'Delete',
              style: TextStyle(color: Color(0xFFFF3B30)),
            ),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() => _isDeleting = true);

    try {
      final response = await SessionManager().post(
        '/backend/deleteAccount.php',
        {'password': _passwordController.text},
      );

      final data = jsonDecode(response.body);

      if (mounted) {
        if (data['success'] == true) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Account deleted'),
              backgroundColor: Color(0xFF34C759),
            ),
          );
          widget.onDeleted?.call();
          // Navigate to login screen
          Navigator.of(context).popUntil((route) => route.isFirst);
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(data['message'] ?? 'Failed to delete account'),
              backgroundColor: const Color(0xFFFF3B30),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $e'),
            backgroundColor: const Color(0xFFFF3B30),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isDeleting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _buildSettingsSubPage(
      context: context,
      title: 'Delete Account',
      bgController: _bgController,
      stars: _stars,
      content: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'This action is permanent and cannot be undone. All your videos, comments, and data will be deleted forever.',
            style: TextStyle(
              color: Colors.white.withOpacity(0.6),
              fontSize: 15,
              height: 1.5,
            ),
          ),
          const SizedBox(height: 24),
          Text(
            'Enter your password to confirm:',
            style: TextStyle(
              color: Colors.white.withOpacity(0.8),
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 12),
          Container(
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.06),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: Colors.white.withOpacity(0.08)),
            ),
            child: TextField(
              controller: _passwordController,
              obscureText: true,
              style: const TextStyle(color: Colors.white, fontSize: 16),
              decoration: InputDecoration(
                hintText: 'Password',
                hintStyle: TextStyle(color: Colors.white.withOpacity(0.3)),
                border: InputBorder.none,
                contentPadding: const EdgeInsets.all(16),
              ),
            ),
          ),
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: _isDeleting ? null : _deleteAccount,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFFF3B30),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              child: _isDeleting
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2,
                      ),
                    )
                  : const Text(
                      'Delete My Account',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}
