import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'constants.dart';

class UserListPage extends StatefulWidget {
  final int userId;
  final String username;
  final int initialTabIndex; // 0 for Followers, 1 for Following

  const UserListPage({
    super.key,
    required this.userId,
    required this.username,
    this.initialTabIndex = 0,
  });

  @override
  State<UserListPage> createState() => _UserListPageState();
}

class _UserListPageState extends State<UserListPage>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final TextEditingController _searchController = TextEditingController();

  List<dynamic> _followers = [];
  List<dynamic> _following = [];
  List<dynamic> _filteredFollowers = [];
  List<dynamic> _filteredFollowing = [];

  bool _isLoadingFollowers = true;
  bool _isLoadingFollowing = true;
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(
      length: 2,
      vsync: this,
      initialIndex: widget.initialTabIndex,
    );
    _tabController.addListener(_handleTabSelection);
    _loadData();
  }

  void _handleTabSelection() {
    if (_tabController.indexIsChanging) {
      if (mounted) setState(() {});
    }
  }

  @override
  void dispose() {
    _tabController.removeListener(_handleTabSelection);
    _tabController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadData() async {
    _fetchFollowers();
    _fetchFollowing();
  }

  Future<void> _fetchFollowers() async {
    try {
      final response = await http.get(
        Uri.parse(
          '${AppConstants.baseUrl}/backend/getSubscribers.php?channel_id=${widget.userId}',
        ),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true) {
          if (mounted) {
            setState(() {
              _followers = data['subscribers'] ?? [];
              _filterFollowers();
              _isLoadingFollowers = false;
            });
          }
        }
      }
    } catch (e) {
      if (mounted) setState(() => _isLoadingFollowers = false);
      print('Error fetching followers: $e');
    }
  }

  Future<void> _fetchFollowing() async {
    try {
      final response = await http.get(
        Uri.parse(
          '${AppConstants.baseUrl}/backend/getSubscriptions.php?user_id=${widget.userId}',
        ),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true) {
          if (mounted) {
            setState(() {
              _following = data['subscriptions'] ?? [];
              _filterFollowing();
              _isLoadingFollowing = false;
            });
          }
        }
      }
    } catch (e) {
      if (mounted) setState(() => _isLoadingFollowing = false);
      print('Error fetching following: $e');
    }
  }

  void _onSearchChanged(String query) {
    setState(() {
      _searchQuery = query.toLowerCase();
      _filterFollowers();
      _filterFollowing();
    });
  }

  void _filterFollowers() {
    if (_searchQuery.isEmpty) {
      _filteredFollowers = List.from(_followers);
    } else {
      _filteredFollowers = _followers.where((u) {
        final username = (u['username'] ?? '').toString().toLowerCase();
        return username.contains(_searchQuery);
      }).toList();
    }
  }

  void _filterFollowing() {
    if (_searchQuery.isEmpty) {
      _filteredFollowing = List.from(_following);
    } else {
      _filteredFollowing = _following.where((u) {
        final username = (u['username'] ?? '').toString().toLowerCase();
        return username.contains(_searchQuery);
      }).toList();
    }
  }

  Future<void> _toggleFollow(int userId, bool isFollowing) async {
    // Optimistic update
    HapticFeedback.lightImpact();
    // Implementation depends on where we are.
    // If we are in "Following" list, unfollowing removes from list?
    // Usually standard UI keeps it but changes button state.
    // But since this is specific to logic:

    // For now, let's just send request and show result?
    // User requested "follow back/unfollow button".

    try {
      final response = await http.post(
        Uri.parse('${AppConstants.baseUrl}/backend/subscribe.php'),
        body: jsonEncode({'channel_id': userId}),
        headers: {'Content-Type': 'application/json'},
      );

      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        // Refresh data or update local state
        // Updating local state complex with lists.
        // Simplest is to reload active tab's data silently
        if (_tabController.index == 0) {
          _fetchFollowers(); // Update followers list (state might not change here visually for button unless we track isFollowing per user)
          // Actually, for "Followers" list, we see people who follow US.
          // The button there should be "Follow Back" (if we don't follow them) or "Unfollow" (if we do).
          // We need to know if WE follow THEM. The current endpoint getSubscribers doesn't tell us if WE follow them.
          // I might need to check that.
          // For "Following" list, we know we follow them. So button is "Unfollow".
          _fetchFollowing(); // To update "Following" list since we just changed subscriptions
        } else {
          _fetchFollowing();
        }
      }
    } catch (e) {
      print('Error toggling follow: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0A0A0F),
      body: Stack(
        children: [
          // Background - reuse logic or simple dark
          Container(color: const Color(0xFF0A0A0F)),

          SafeArea(
            child: Column(
              children: [
                // Header
                _buildHeader(),

                // Tabs
                _buildTabs(),

                // Search Bar
                _buildSearchBar(),

                // List Content
                Expanded(
                  child: TabBarView(
                    controller: _tabController,
                    children: [
                      _buildUserList(
                        _filteredFollowers,
                        _isLoadingFollowers,
                        isFollowersTab: true,
                      ),
                      _buildUserList(
                        _filteredFollowing,
                        _isLoadingFollowing,
                        isFollowersTab: false,
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          GestureDetector(
            onTap: () => Navigator.pop(context),
            child: Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.arrow_back, color: Colors.white),
            ),
          ),
          const SizedBox(width: 16),
          Text(
            widget.username,
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.bold,
              color: Colors.white,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTabs() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      height: 40,
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.05),
        borderRadius: BorderRadius.circular(20),
      ),
      child: TabBar(
        controller: _tabController,
        indicator: BoxDecoration(
          color: const Color(0xFF0071E3),
          borderRadius: BorderRadius.circular(20),
        ),
        labelColor: Colors.white,
        unselectedLabelColor: Colors.white.withOpacity(0.5),
        labelStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
        tabs: [
          Tab(
            text: 'Followers',
          ), // ${_followers.length} would be nice but loading async
          Tab(text: 'Following'),
        ],
      ),
    );
  }

  Widget _buildSearchBar() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.05),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.white.withOpacity(0.1)),
      ),
      child: TextField(
        controller: _searchController,
        onChanged: _onSearchChanged,
        style: const TextStyle(color: Colors.white),
        decoration: InputDecoration(
          icon: Icon(
            Icons.search,
            color: Colors.white.withOpacity(0.4),
            size: 20,
          ),
          hintText: 'Search people...',
          hintStyle: TextStyle(color: Colors.white.withOpacity(0.4)),
          border: InputBorder.none,
          contentPadding: const EdgeInsets.symmetric(vertical: 12),
        ),
      ),
    );
  }

  Widget _buildUserList(
    List<dynamic> users,
    bool isLoading, {
    required bool isFollowersTab,
  }) {
    if (isLoading) {
      return const Center(
        child: CircularProgressIndicator(
          valueColor: AlwaysStoppedAnimation(Color(0xFF0071E3)),
        ),
      );
    }

    if (users.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              isFollowersTab
                  ? FontAwesomeIcons.users
                  : FontAwesomeIcons.userPlus,
              size: 48,
              color: Colors.white.withOpacity(0.2),
            ),
            const SizedBox(height: 16),
            Text(
              isFollowersTab
                  ? (_searchQuery.isNotEmpty
                        ? 'No followers found'
                        : 'No followers yet')
                  : (_searchQuery.isNotEmpty
                        ? 'No following found'
                        : 'Not following anyone'),
              style: TextStyle(
                color: Colors.white.withOpacity(0.5),
                fontSize: 14,
              ),
            ),
          ],
        ),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      itemCount: users.length,
      itemBuilder: (context, index) {
        final user = users[index];
        return _buildUserItem(user, isFollowersTab);
      },
    );
  }

  Widget _buildUserItem(Map<String, dynamic> user, bool isFollowersTab) {
    final username = user['username'] ?? 'Unknown';
    final profilePic = user['profile_picture'];
    final userId = user['id'];

    // Logic for button:
    // If "Following" tab: we are following them. Button: "Unfollow" (gray)
    // If "Followers" tab: they follow us. We don't know if we follow them from this endpoint data alone.
    // Ideally we assume "Follow Back" (blue) unless we check 'following' list.
    // For simplicity, let's check if this user exists in our _following list!

    bool amIFollowing = false;
    if (isFollowersTab) {
      // Check if this follower is also in my following list
      amIFollowing = _following.any((u) => u['id'] == userId);
    } else {
      // In Following tab, I am definitely following them
      amIFollowing = true;
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      child: Row(
        children: [
          // Avatar
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: Colors.white.withOpacity(0.1)),
            ),
            child: ClipOval(
              child: profilePic != null
                  ? Image.network(
                      AppConstants.sanitizeUrl(profilePic),
                      fit: BoxFit.cover,
                      errorBuilder: (_, _, _) => _buildDefaultAvatar(username),
                    )
                  : _buildDefaultAvatar(username),
            ),
          ),
          const SizedBox(width: 12),
          // Info
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  username,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w600,
                    fontSize: 16,
                  ),
                ),
                Text(
                  '@$username',
                  style: TextStyle(
                    color: Colors.white.withOpacity(0.5),
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          ),
          // Action Button
          GestureDetector(
            onTap: () => _toggleFollow(userId, amIFollowing),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
              decoration: BoxDecoration(
                color: amIFollowing
                    ? Colors.white.withOpacity(0.1)
                    : const Color(0xFF0071E3),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                amIFollowing ? 'Unfollow' : 'Follow Back',
                style: TextStyle(
                  color: amIFollowing ? Colors.white : Colors.white,
                  fontWeight: FontWeight.w600,
                  fontSize: 12,
                ),
              ),
            ),
          ),
        ],
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
            fontSize: 20,
            fontWeight: FontWeight.bold,
            color: Color(0xFF0071E3),
          ),
        ),
      ),
    );
  }
}
