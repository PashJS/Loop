import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'dart:ui' as ui;
import 'dart:math';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'stars.dart';
import 'home_page.dart';
import 'constants.dart';
import 'notification_service.dart';
import 'background_service.dart';

import 'session_manager.dart';

/// Keyboard-responsive content layer for onboarding
/// Isolates MediaQuery.viewInsetsOf dependency to prevent full tree rebuilds
class _OnboardingContentLayer extends StatelessWidget {
  final Widget child;

  const _OnboardingContentLayer({required this.child});

  @override
  Widget build(BuildContext context) {
    // Only this widget rebuilds when keyboard appears/disappears
    final keyboardHeight = MediaQuery.viewInsetsOf(context).bottom;

    return Positioned(
      top: 0,
      left: 0,
      right: 0,
      // Shrink bottom when keyboard is visible to push content up
      bottom: keyboardHeight,
      child: child,
    );
  }
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize session manager
  await SessionManager().init();
  debugPrint("Initial Cookie: ${SessionManager().headers['Cookie']}");

  // Initialize notification service safely
  try {
    await NotificationService().init();
  } catch (e) {
    debugPrint('Failed to initialize NotificationService: $e');
  }

  // Initialize Background Service
  try {
    await initializeBackgroundService();
  } catch (e) {
    debugPrint('Failed to initialize Background Service: $e');
  }

  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(statusBarColor: Colors.transparent),
  );
  runApp(const LoopApp());
}

class LoopApp extends StatelessWidget {
  const LoopApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Loop',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        brightness: Brightness.dark,
        primaryColor: const Color(0xFF0071E3),
        scaffoldBackgroundColor: const Color(0xFF000000),
        fontFamily: 'SanFrancisco',
        fontFamilyFallback: const ['AppleEmoji'],
      ),
      home: const OnboardingFlow(),
    );
  }
}

class OnboardingFlow extends StatefulWidget {
  const OnboardingFlow({super.key});

  @override
  State<OnboardingFlow> createState() => _OnboardingFlowState();
}

class _OnboardingFlowState extends State<OnboardingFlow>
    with TickerProviderStateMixin {
  // Controllers
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _nameController = TextEditingController();
  final _lastNameController = TextEditingController();
  final _codeController = TextEditingController();
  final _confirmPassController = TextEditingController();

  // Focus Nodes
  final _emailFocus = FocusNode();
  final _passwordFocus = FocusNode();
  final _nameFocus = FocusNode();
  final _lastNameFocus = FocusNode();
  final _codeFocus = FocusNode();

  Map<String, dynamic>? _authenticatedUser;

  late List<Star> _stars;

  final GoogleSignIn _googleSignIn = GoogleSignIn(
    // serverClientId is the Web Client ID - used to get idToken for backend verification
    // Do NOT set clientId for Android - it uses SHA-1 fingerprint from Google Cloud Console
    serverClientId:
        '687042225048-8d7sfa8sp9kt9am5iqleolh4l81m53e5.apps.googleusercontent.com',
    scopes: ['email', 'profile'],
  );

  // 0=Welcome
  // 1-3=Login Flow
  // 10-14=Create Account Flow
  int _step = 0;
  bool _isLoading = false;

  late AnimationController _bgController;
  late AnimationController _contentController;
  late AnimationController _iconController;
  late AnimationController _logoSpinController;

  @override
  void initState() {
    super.initState();
    SystemChrome.setPreferredOrientations([
      DeviceOrientation.portraitUp,
      DeviceOrientation.portraitDown,
    ]);

    // Optimizing durations for snappier feel
    _bgController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 20),
    )..repeat();
    _contentController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 300),
    )..forward();
    _iconController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    );
    _logoSpinController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 800),
    );
    _stars = generateStars(); // Pre-calculate stars

    _checkSession();
  }

  Future<void> _checkSession() async {
    final prefs = await SharedPreferences.getInstance();
    final userJson = prefs.getString('auth_user');
    if (userJson != null && userJson != 'null') {
      try {
        final userData = jsonDecode(userJson);
        if (userData != null) {
          setState(() => _authenticatedUser = userData);
          _navigateToHome(userData);
        }
      } catch (e) {
        prefs.remove('auth_user');
      }
    }
  }

  Future<void> _saveSession(Map<String, dynamic>? user) async {
    if (user == null) return;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('auth_user', jsonEncode(user));
  }

  @override
  void dispose() {
    _bgController.dispose();
    _contentController.dispose();
    _iconController.dispose();
    _logoSpinController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _nameController.dispose();
    _lastNameController.dispose();
    _codeController.dispose();
    _confirmPassController.dispose();
    _emailFocus.dispose();
    _passwordFocus.dispose();
    _nameFocus.dispose();
    _lastNameFocus.dispose();
    _codeFocus.dispose();
    super.dispose();
  }

  void _goToStep(int step) {
    _contentController.reverse().then((_) {
      setState(() => _step = step);
      _contentController.forward();
      _iconController.reset();
      _iconController.forward();

      // Auto-focus logic
      if (step == 1) _emailFocus.requestFocus(); // Login: Email
      if (step == 2) _passwordFocus.requestFocus(); // Login: Pass

      if (step == 10) _nameFocus.requestFocus(); // Create: Name
      if (step == 11) _emailFocus.requestFocus(); // Create: Email
      if (step == 12 || step == 4) _codeFocus.requestFocus(); // Codes
      if (step == 13) _passwordFocus.requestFocus(); // Create: Pass
    });
  }

  void _spinLogo() {
    if (!_logoSpinController.isAnimating) _logoSpinController.forward(from: 0);
  }

  // --- API CALLS ---
  Future<void> _login() async {
    setState(() => _isLoading = true);
    try {
      final res = await http
          .post(
            Uri.parse('${AppConstants.baseUrl}/backend/login.php'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({
              'email': _emailController.text.trim(),
              'password': _passwordController.text,
            }),
          )
          .timeout(const Duration(seconds: 30));

      debugPrint('Login response: ${res.statusCode}, ${res.body}');

      if (res.statusCode != 200) {
        throw 'Server error: ${res.statusCode}';
      }

      final data = jsonDecode(res.body);
      if (data['success'] == true) {
        // Save session cookie if present
        final cookie = res.headers['set-cookie'];
        if (cookie != null) {
          await SessionManager().saveCookie(cookie);
        }
        if (data['session_id'] != null) {
          await SessionManager().saveSessionId(data['session_id']);
        }

        if (data['two_factor_required'] == true) {
          _codeController.clear();
          _goToStep(4);
          return;
        }
        if (data['user'] != null) {
          setState(() => _authenticatedUser = data['user']);
          await _saveSession(data['user']);
          _goToStep(3);
        } else {
          _showError('Login successful, but user data is missing.');
        }
      } else {
        _showError(data['message'] ?? 'Login failed');
      }
    } catch (e) {
      debugPrint('Login error details: $e');
      _showError('Login failed. Please check your connection.');
    } finally {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _verify2FA() async {
    setState(() => _isLoading = true);
    try {
      final res = await http
          .post(
            Uri.parse('${AppConstants.baseUrl}/backend/verify_2fa.php'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({
              'email': _emailController.text.trim(),
              'code': _codeController.text.trim(),
            }),
          )
          .timeout(const Duration(seconds: 30));

      final data = jsonDecode(res.body);
      if (data['success'] == true && data['user'] != null) {
        // Save session cookie if present
        final cookie = res.headers['set-cookie'];
        if (cookie != null) {
          await SessionManager().saveCookie(cookie);
        }
        if (data['session_id'] != null) {
          await SessionManager().saveSessionId(data['session_id']);
        }

        setState(() => _authenticatedUser = data['user']);
        await _saveSession(data['user']);
        _goToStep(3);
      } else {
        _showError(data['message'] ?? 'Invalid code');
      }
    } catch (e) {
      _showError('Network error: $e');
    } finally {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _sendCode() async {
    setState(() => _isLoading = true);
    try {
      final res = await http
          .post(
            Uri.parse(
              '${AppConstants.baseUrl}/backend/send_verification_code.php',
            ),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({'email': _emailController.text.trim()}),
          )
          .timeout(const Duration(seconds: 45)); // Sending email takes time

      final data = jsonDecode(res.body);
      if (data['success'] == true) {
        _goToStep(12);
        _showError('Code sent to email!', color: Colors.green);
      } else {
        _showError(data['message'] ?? 'Failed to send code');
      }
    } catch (e) {
      _showError('Network error: $e');
    } finally {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _verifyCode() async {
    setState(() => _isLoading = true);
    try {
      final res = await http
          .post(
            Uri.parse('${AppConstants.baseUrl}/backend/verify_code.php'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({
              'email': _emailController.text.trim(),
              'code': _codeController.text.trim(),
            }),
          )
          .timeout(const Duration(seconds: 30));

      final data = jsonDecode(res.body);
      if (data['success'] == true) {
        _goToStep(13);
      } else {
        _showError(data['message'] ?? 'Invalid code');
      }
    } catch (e) {
      _showError('Network error: $e');
    } finally {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _createAccount() async {
    if (_passwordController.text != _confirmPassController.text) {
      _showError("Passwords do not match");
      return;
    }
    setState(() => _isLoading = true);

    try {
      final res = await http
          .post(
            Uri.parse('${AppConstants.baseUrl}/backend/register_full.php'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({
              'first_name': _nameController.text.trim(),
              'last_name': _lastNameController.text.trim(),
              'email': _emailController.text.trim(),
              'password': _passwordController.text,
              'code': _codeController.text.trim(),
            }),
          )
          .timeout(const Duration(seconds: 45));

      final data = jsonDecode(res.body);
      if (data['success'] == true) {
        // Save session cookie if present
        final cookie = res.headers['set-cookie'];
        if (cookie != null) {
          await SessionManager().saveCookie(cookie);
        }
        if (data['session_id'] != null) {
          await SessionManager().saveSessionId(data['session_id']);
        }

        setState(() => _authenticatedUser = data['user']);
        await _saveSession(data['user']);
        _goToStep(14);
      } else {
        _showError(data['message'] ?? 'Registration failed');
      }
    } catch (e) {
      _showError('Network error: $e');
    } finally {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _handleGoogleSignIn() async {
    try {
      final GoogleSignInAccount? googleUser = await _googleSignIn.signIn();
      if (googleUser == null) return; // Canceled

      setState(() => _isLoading = true);
      final GoogleSignInAuthentication googleAuth =
          await googleUser.authentication;

      // Use idToken for backend verification (more reliable on mobile)
      // accessToken can be null on some platforms
      final token = googleAuth.idToken ?? googleAuth.accessToken;

      if (token == null) {
        _showError('Failed to get authentication token');
        await _googleSignIn.signOut();
        return;
      }

      // Backend Verification
      final res = await http
          .post(
            Uri.parse('${AppConstants.baseUrl}/backend/auth_google_mobile.php'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({
              'id_token': googleAuth.idToken,
              'access_token': googleAuth.accessToken,
            }),
          )
          .timeout(const Duration(seconds: 30));

      final data = jsonDecode(res.body);
      if (data['success'] == true) {
        // Save session cookie if present
        final cookie = res.headers['set-cookie'];
        if (cookie != null) {
          await SessionManager().saveCookie(cookie);
        }
        if (data['session_id'] != null) {
          await SessionManager().saveSessionId(data['session_id']);
        }

        setState(() => _authenticatedUser = data['user']);
        await _saveSession(data['user']);
        _goToStep(3);
      } else {
        _showError('Backend Error: ${data['message']}');
        await _googleSignIn.signOut();
      }
    } catch (error) {
      _showError('Auth Exception: $error');
    } finally {
      setState(() => _isLoading = false);
    }
  }

  void _showError(String msg, {Color color = Colors.redAccent}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg),
        backgroundColor: color.withOpacity(0.9),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );
  }

  void _navigateToHome(Map<String, dynamic>? user) {
    Navigator.of(context).pushReplacement(
      PageRouteBuilder(
        pageBuilder: (context, animation, secondaryAnimation) =>
            HomePage(user: user),
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          return FadeTransition(
            opacity: CurvedAnimation(
              parent: animation,
              curve: Curves.easeOutCubic,
            ),
            child: child,
          );
        },
        transitionDuration: const Duration(milliseconds: 400),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvoked: (bool didPop) {
        if (didPop) return;
        // Use View.of(context).viewInsets for keyboard detection
        // This doesn't trigger rebuilds like MediaQuery.of(context).viewInsets
        final viewInsets = View.of(context).viewInsets;
        if (viewInsets.bottom > 0) {
          FocusScope.of(context).unfocus();
          return;
        }

        // Navigation Logic
        // Welcome(0) -> Exit
        if (_step == 0) SystemNavigator.pop();

        // Login Flow (1,2,3)
        if (_step == 1) _goToStep(0);
        if (_step == 2) _goToStep(1);
        if (_step == 3) _goToStep(0);

        // Create Account Flow (10,11,12,13,14)
        if (_step == 10) _goToStep(0);
        if (_step == 11) _goToStep(10);
        if (_step == 12) _goToStep(11);
        if (_step == 13) _goToStep(12);
        if (_step == 14) _goToStep(0);
      },
      child: ScrollConfiguration(
        behavior: const ScrollBehavior().copyWith(overscroll: false),
        child: Scaffold(
          resizeToAvoidBottomInset: false,
          backgroundColor: Colors.black,
          body: Stack(
            fit: StackFit.expand,
            children: [
              // 1. BACKGROUND GRADIENTS (Static - Zero Animation Cost)
              Container(
                decoration: const BoxDecoration(
                  gradient: RadialGradient(
                    center: Alignment(-0.4, -0.6),
                    radius: 1.2,
                    colors: [Color(0xFF0A0A1A), Color(0xFF000000)],
                  ),
                ),
              ),
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

              // 2. STARS (Animated) - Wrapped in RepaintBoundary to isolate repaints
              RepaintBoundary(
                child: AnimatedBuilder(
                  animation: _bgController,
                  builder: (_, _) => CustomPaint(
                    painter: NebulaPainter(_bgController.value, _stars),
                    size: Size.infinite,
                  ),
                ),
              ),
              // 3. CONTENT LAYER - Uses isolated keyboard observer
              // Only this layer responds to keyboard, backgrounds remain static
              _OnboardingContentLayer(
                child: SafeArea(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 28),
                    child: Column(
                      children: [
                        const Spacer(flex: 2),
                        if (_step == 0) _buildLogo(),
                        if (_step == 0) const SizedBox(height: 48),
                        if (_step < 4) _buildDots(), // Dots for login
                        const SizedBox(height: 40),
                        FadeTransition(
                          opacity: CurvedAnimation(
                            parent: _contentController,
                            curve: Curves.easeOutCubic,
                          ),
                          child: SlideTransition(
                            position:
                                Tween<Offset>(
                                  begin: const Offset(0, 0.08),
                                  end: Offset.zero,
                                ).animate(
                                  CurvedAnimation(
                                    parent: _contentController,
                                    curve: Curves.easeOutCubic,
                                  ),
                                ),
                            child: _buildStepContent(),
                          ),
                        ),
                        const Spacer(flex: 3),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildLogo() {
    return GestureDetector(
      onTap: _spinLogo,
      child: AnimatedBuilder(
        animation: _logoSpinController,
        builder: (_, _) {
          final double angle =
              CurvedAnimation(
                parent: _logoSpinController,
                curve: Curves.elasticOut,
              ).value *
              2 *
              pi;
          return Transform.rotate(
            angle: angle,
            child: SizedBox(
              width: 50,
              height: 50,
              child: CustomPaint(painter: LoopLogoPainter()),
            ),
          );
        },
      ),
    );
  }

  Widget _buildDots() {
    if (_step >= 10) return const SizedBox.shrink();
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List.generate(4, (i) {
        final isActive = i == _step;
        final isDone = i < _step;
        return AnimatedContainer(
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOutCubic,
          margin: const EdgeInsets.symmetric(horizontal: 4),
          width: isActive ? 28 : 8,
          height: 8,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(4),
            color: isActive
                ? const Color(0xFF0071E3)
                : isDone
                ? const Color(0xFF00D26A)
                : Colors.white.withOpacity(0.15),
            boxShadow: isActive
                ? [
                    BoxShadow(
                      color: const Color(0xFF0071E3).withOpacity(0.6),
                      blurRadius: 12,
                    ),
                  ]
                : null,
          ),
        );
      }),
    );
  }

  Widget _buildStepContent() {
    switch (_step) {
      case 0:
        return _stepWelcome();
      // Login Flow
      case 1:
        return _stepEmail();
      case 2:
        return _stepPassword();
      case 3:
        return _stepSuccess();
      case 4:
        return _stepTwoFactor();

      // Create Account Flow
      case 10:
        return _stepCreateName();
      case 11:
        return _stepCreateEmail();
      case 12:
        return _stepCreateCode();
      case 13:
        return _stepCreatePassword();
      case 14:
        return _stepSuccess(msg: "Account Created!");

      default:
        return const SizedBox.shrink();
    }
  }

  // --- STEPS ---

  Widget _stepWelcome() {
    return Column(
      children: [
        const Text(
          'Welcome to\nLoop',
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: 36,
            fontWeight: FontWeight.w700,
            height: 1.2,
            letterSpacing: -1.5,
          ),
        ),
        const SizedBox(height: 12),
        Text(
          'Your universe of content awaits.',
          style: TextStyle(fontSize: 16, color: Colors.white.withOpacity(0.5)),
        ),
        const SizedBox(height: 40),
        _primaryButton('Sign In', () => _goToStep(1)),
        const SizedBox(height: 12),
        _glassButton('Create Account', () => _goToStep(10)),

        const SizedBox(height: 24),
        Row(
          children: [
            Expanded(child: Divider(color: Colors.white.withOpacity(0.1))),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Text(
                "OR",
                style: TextStyle(
                  color: Colors.white.withOpacity(0.4),
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            Expanded(child: Divider(color: Colors.white.withOpacity(0.1))),
          ],
        ),
        const SizedBox(height: 24),

        _googleButton(() {
          _handleGoogleSignIn();
        }),
        const SizedBox(height: 24),
        GestureDetector(
          onTap: () => _navigateToHome(null), // Guest Login
          child: Text(
            "Continue as Guest",
            style: TextStyle(
              color: Colors.white.withOpacity(0.5),
              fontSize: 15,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
      ],
    );
  }

  // LOGIN: EMAIL
  Widget _stepEmail() {
    return _genericStep(
      painterBuilder: (t) => AnimatedEmailIconPainter(t),
      title: 'Enter your email',
      child: Column(
        children: [
          _inputField(
            _emailController,
            'Email address',
            Icons.mail_outline,
            focusNode: _emailFocus,
          ),
          const SizedBox(height: 32),
          _primaryButton('Continue', () {
            if (_emailController.text.contains('@')) {
              _goToStep(2);
            } else {
              _showError('Please enter a valid email');
            }
          }),
        ],
      ),
      onBack: () => _goToStep(0),
    );
  }

  // LOGIN: PASSWORD
  Widget _stepPassword() {
    return _genericStep(
      painterBuilder: (t) => AnimatedKeyIconPainter(t),
      title: 'Enter your password',
      child: Column(
        children: [
          _inputField(
            _passwordController,
            'Password',
            Icons.lock_outline,
            isPassword: true,
            focusNode: _passwordFocus,
          ),
          const SizedBox(height: 32),
          _primaryButton(
            _isLoading ? 'Signing in...' : 'Sign In',
            _login,
            isLoading: _isLoading,
          ),
        ],
      ),
      onBack: () => _goToStep(1),
    );
  }

  // CREATE: NAME (Step 10)
  Widget _stepCreateName() {
    return _genericStep(
      painterBuilder: (t) => AnimatedIDCardPainter(t),
      title: 'What should we call you?',
      child: Column(
        children: [
          _inputField(
            _nameController,
            'First Name',
            Icons.person_outline,
            focusNode: _nameFocus,
          ),
          const SizedBox(height: 16),
          _inputField(
            _lastNameController,
            'Last Name',
            Icons.person_outline,
            focusNode: _lastNameFocus,
          ),
          const SizedBox(height: 32),
          _primaryButton('Continue', () {
            if (_nameController.text.isNotEmpty) {
              _goToStep(11);
            } else {
              _showError('Please enter your name');
            }
          }),
        ],
      ),
      onBack: () => _goToStep(0),
    );
  }

  // CREATE: EMAIL (Step 11)
  Widget _stepCreateEmail() {
    return _genericStep(
      painterBuilder: (t) => AnimatedEmailIconPainter(t),
      title: 'What\'s your email?',
      child: Column(
        children: [
          _inputField(
            _emailController,
            'Email address',
            Icons.mail_outline,
            focusNode: _emailFocus,
          ),
          const SizedBox(height: 32),
          _primaryButton('Send Code', () {
            if (_emailController.text.contains('@')) {
              _sendCode();
            } else {
              _showError('Invalid email');
            }
          }, isLoading: _isLoading),
        ],
      ),
      onBack: () => _goToStep(10),
    );
  }

  // CREATE: CODE (Step 12)
  Widget _stepCreateCode() {
    return _genericStep(
      painterBuilder: (t) => AnimatedShieldPainter(t),
      title: 'For security.',
      subtitle: 'We sent a code to your email.',
      child: Column(
        children: [
          _inputField(
            _codeController,
            'Security Code',
            Icons.security,
            focusNode: _codeFocus,
          ),
          const SizedBox(height: 32),
          _primaryButton('Verify', () => _verifyCode(), isLoading: _isLoading),
        ],
      ),
      onBack: () => _goToStep(11),
    );
  }

  // CREATE: PASSWORD (Step 13)
  Widget _stepCreatePassword() {
    return _genericStep(
      painterBuilder: (t) => AnimatedKeyIconPainter(t),
      title: 'Almost done!',
      subtitle: 'Create a strong password.',
      child: Column(
        children: [
          _inputField(
            _passwordController,
            'Password',
            Icons.lock_outline,
            isPassword: true,
            focusNode: _passwordFocus,
          ),
          const SizedBox(height: 16),
          _inputField(
            _confirmPassController,
            'Confirm Password',
            Icons.lock_outline,
            isPassword: true,
          ),
          const SizedBox(height: 32),
          _primaryButton(
            'Create Account',
            _createAccount,
            isLoading: _isLoading,
          ),
        ],
      ),
      onBack: () => _goToStep(12),
    );
  }

  Widget _stepSuccess({String msg = "You're in!"}) {
    return Column(
      children: [
        TweenAnimationBuilder<double>(
          tween: Tween(begin: 0, end: 1),
          duration: const Duration(milliseconds: 800),
          curve: Curves.elasticOut,
          builder: (_, value, _) => Transform.scale(
            scale: value,
            child: CustomPaint(
              size: const Size(100, 100),
              painter: AnimatedCheckmarkPainter(value),
            ),
          ),
        ),
        const SizedBox(height: 32),
        Text(
          msg,
          style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 8),
        Text(
          'Welcome to Loop.',
          style: TextStyle(color: Colors.white.withOpacity(0.5), fontSize: 16),
        ),
        const SizedBox(height: 48),
        _primaryButton('Go to Home', () {
          _navigateToHome(_authenticatedUser);
        }),
      ],
    );
  }

  // LOGIN: 2FA (Step 4)
  Widget _stepTwoFactor() {
    return _genericStep(
      painterBuilder: (t) => AnimatedShieldPainter(t),
      title: 'Account Protected',
      subtitle: 'Enter the code we sent to your email.',
      child: Column(
        children: [
          _inputField(
            _codeController,
            '6-digit code',
            Icons.lock_person_outlined,
            focusNode: _codeFocus,
          ),
          const SizedBox(height: 32),
          _primaryButton('Verify & Sign In', _verify2FA, isLoading: _isLoading),
        ],
      ),
      onBack: () => _goToStep(2),
    );
  }

  Widget _genericStep({
    required CustomPainter Function(double) painterBuilder,
    required String title,
    String? subtitle,
    required Widget child,
    required VoidCallback onBack,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _backButton(onBack),
        const SizedBox(height: 10),
        Center(
          child: AnimatedBuilder(
            animation: _iconController,
            builder: (_, _) => CustomPaint(
              size: const Size(80, 80),
              painter: painterBuilder(_iconController.value),
            ),
          ),
        ),
        const SizedBox(height: 24),
        Text(
          title,
          style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w700),
        ),
        if (subtitle != null) ...[
          const SizedBox(height: 8),
          Text(
            subtitle,
            style: TextStyle(color: Colors.white.withOpacity(0.5)),
          ),
        ],
        const SizedBox(height: 32),
        child,
      ],
    );
  }

  Widget _backButton(VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.05),
          borderRadius: BorderRadius.circular(12),
        ),
        child: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
      ),
    );
  }

  Widget _inputField(
    TextEditingController ctrl,
    String hint,
    IconData icon, {
    bool isPassword = false,
    FocusNode? focusNode,
  }) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: BackdropFilter(
        filter: ui.ImageFilter.blur(sigmaX: 10, sigmaY: 10),
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white.withOpacity(0.06),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: Colors.white.withOpacity(0.1)),
          ),
          child: TextField(
            focusNode: focusNode,
            controller: ctrl,
            obscureText: isPassword,
            style: const TextStyle(fontSize: 17),
            decoration: InputDecoration(
              prefixIcon: Icon(icon, color: Colors.white38, size: 22),
              hintText: hint,
              hintStyle: TextStyle(color: Colors.white.withOpacity(0.25)),
              border: InputBorder.none,
              contentPadding: const EdgeInsets.symmetric(
                vertical: 18,
                horizontal: 20,
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _primaryButton(
    String text,
    VoidCallback onTap, {
    bool isLoading = false,
    Color? color,
    Color? textColor,
  }) {
    return GestureDetector(
      onTap: isLoading ? null : onTap,
      child: Container(
        width: double.infinity,
        height: 56,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          gradient: color == null
              ? const LinearGradient(
                  colors: [Color(0xFF0088FF), Color(0xFF0055DD)],
                )
              : null,
          color: color,
          boxShadow: color == null
              ? [
                  BoxShadow(
                    color: const Color(0xFF0071E3).withOpacity(0.4),
                    blurRadius: 20,
                    offset: const Offset(0, 8),
                  ),
                ]
              : null,
        ),
        child: Center(
          child: isLoading
              ? const SizedBox(
                  width: 24,
                  height: 24,
                  child: CircularProgressIndicator(
                    strokeWidth: 2.5,
                    valueColor: AlwaysStoppedAnimation(Colors.white),
                  ),
                )
              : Text(
                  text,
                  style: TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w600,
                    color: textColor ?? Colors.white,
                  ),
                ),
        ),
      ),
    );
  }

  Widget _glassButton(String text, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: BackdropFilter(
          filter: ui.ImageFilter.blur(sigmaX: 10, sigmaY: 10),
          child: Container(
            width: double.infinity,
            height: 56,
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.08),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: Colors.white.withOpacity(0.2),
                width: 1,
              ),
            ),
            child: Center(
              child: Text(
                text,
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w600,
                  color: Colors.white,
                  letterSpacing: 0.5,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _googleButton(VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: double.infinity,
        height: 56,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          color: Colors.white,
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CustomPaint(size: const Size(24, 24), painter: GoogleLogoPainter()),
            const SizedBox(width: 12),
            const Text(
              "Continue with Google",
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w600,
                color: Colors.black87,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ============ PAINTERS ============

class GoogleLogoPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    // Google Logo SVG Paths (Standard 24x24 viewbox)
    // Scale to fit the size
    final double scaleX = size.width / 24.0;
    final double scaleY = size.height / 24.0;

    canvas.save();
    canvas.scale(scaleX, scaleY);

    final Paint paint = Paint()..style = PaintingStyle.fill;

    // Blue
    Path bluePath = Path();
    bluePath.moveTo(22.56, 12.25);
    bluePath.cubicTo(22.56, 11.47, 22.49, 10.72, 22.36, 10.0);
    bluePath.lineTo(12.0, 10.0);
    bluePath.lineTo(12.0, 14.26);
    bluePath.lineTo(17.92, 14.26);
    bluePath.cubicTo(17.66, 15.63, 16.88, 16.79, 15.71, 17.57);
    bluePath.lineTo(15.71, 20.34);
    bluePath.lineTo(19.28, 20.34);
    bluePath.cubicTo(21.36, 18.42, 22.56, 15.6, 22.56, 12.25);
    bluePath.close();
    paint.color = const Color(0xFF4285F4);
    canvas.drawPath(bluePath, paint);

    // Green
    Path greenPath = Path();
    greenPath.moveTo(12.0, 23.0);
    greenPath.cubicTo(14.97, 23.0, 17.46, 22.02, 19.28, 20.34);
    greenPath.lineTo(15.71, 17.57);
    greenPath.cubicTo(14.73, 18.23, 13.48, 18.63, 12.0, 18.63);
    greenPath.cubicTo(9.14, 18.63, 6.71, 16.7, 5.84, 14.1);
    greenPath.lineTo(2.18, 14.1);
    greenPath.lineTo(2.18, 16.94);
    greenPath.cubicTo(3.99, 20.53, 7.7, 23.0, 12.0, 23.0);
    greenPath.close();
    paint.color = const Color(0xFF34A853);
    canvas.drawPath(greenPath, paint);

    // Yellow
    Path yellowPath = Path();
    yellowPath.moveTo(5.84, 14.1);
    yellowPath.cubicTo(
      5.62,
      13.44,
      5.49,
      12.74,
      5.49,
      12.01,
    ); // 12.01 to fix precision
    yellowPath.cubicTo(5.49, 11.28, 5.62, 10.58, 5.84, 9.92);
    yellowPath.lineTo(5.84, 7.07);
    yellowPath.lineTo(2.18, 7.07);
    yellowPath.cubicTo(1.43, 8.55, 1.0, 10.22, 1.0, 12.0);
    yellowPath.cubicTo(1.0, 13.78, 1.43, 15.45, 2.18, 16.94);
    yellowPath.lineTo(5.84, 14.1);
    yellowPath.close();
    paint.color = const Color(0xFFFBBC05);
    canvas.drawPath(yellowPath, paint);

    // Red
    Path redPath = Path();
    redPath.moveTo(12.0, 5.38);
    redPath.cubicTo(13.62, 5.38, 15.06, 5.94, 16.21, 7.02);
    redPath.lineTo(19.36, 3.87);
    redPath.cubicTo(17.45, 2.09, 14.97, 1.0, 12.0, 1.0);
    redPath.cubicTo(7.7, 1.0, 3.99, 3.47, 2.18, 7.07);
    redPath.lineTo(5.84, 9.92);
    redPath.cubicTo(6.71, 7.32, 9.14, 5.38, 12.0, 5.38);
    redPath.close();
    paint.color = const Color(0xFFEA4335);
    canvas.drawPath(redPath, paint);

    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant CustomPainter old) => false;
}

class LoopLogoPainter extends CustomPainter {
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
  bool shouldRepaint(covariant CustomPainter old) => false;
}

class AnimatedEmailIconPainter extends CustomPainter {
  final double t; // 0.0 to 1.0
  AnimatedEmailIconPainter(this.t);
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFF0071E3)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.5
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    final w = size.width;
    final h = size.height;
    double bounceY = 0;
    if (t < 0.2) {
      bounceY = -sin(t * 5 * pi) * 10;
    } else if (t >= 0.2 && t < 0.35) {
      double localT = (t - 0.2) / 0.15;
      bounceY = -sin(localT * pi) * 4;
    }
    canvas.save();
    canvas.translate(0, bounceY);
    double letterSlide = 0;
    if (t > 0.6) {
      double slideT = (t - 0.6) / 0.2;
      slideT = Curves.easeOutBack.transform(slideT.clamp(0.0, 1.0));
      letterSlide = slideT * (h * 0.35);
    }
    final letterRect = Rect.fromLTWH(
      w * 0.2,
      h * 0.3 - letterSlide,
      w * 0.6,
      h * 0.4,
    );
    canvas.drawRect(
      letterRect,
      Paint()
        ..color = Colors.white.withOpacity(0.8)
        ..style = PaintingStyle.fill,
    );
    final linePaint = Paint()
      ..color = const Color(0xFF0071E3).withOpacity(0.3)
      ..strokeWidth = 2;
    canvas.drawLine(
      Offset(w * 0.3, h * 0.4 - letterSlide),
      Offset(w * 0.7, h * 0.4 - letterSlide),
      linePaint,
    );
    canvas.drawLine(
      Offset(w * 0.3, h * 0.5 - letterSlide),
      Offset(w * 0.6, h * 0.5 - letterSlide),
      linePaint,
    );
    final bodyPath = Path()
      ..moveTo(w * 0.1, h * 0.35)
      ..lineTo(w * 0.1, h * 0.75)
      ..quadraticBezierTo(w * 0.1, h * 0.85, w * 0.2, h * 0.85)
      ..lineTo(w * 0.8, h * 0.85)
      ..quadraticBezierTo(w * 0.9, h * 0.85, w * 0.9, h * 0.75)
      ..lineTo(w * 0.9, h * 0.35);
    canvas.drawPath(bodyPath, Paint()..color = const Color(0xFF000000));
    canvas.drawPath(bodyPath, paint);
    double flapProgress = 0;
    if (t > 0.35) {
      double flapT = (t - 0.35) / 0.25;
      flapProgress = Curves.easeInOutCubic.transform(flapT.clamp(0.0, 1.0));
    }
    double flapY = h * 0.6 - (flapProgress * h * 0.5);
    final flapPath = Path()
      ..moveTo(w * 0.1, h * 0.35)
      ..lineTo(w * 0.5, flapY)
      ..lineTo(w * 0.9, h * 0.35);
    canvas.drawPath(flapPath, paint);
    if (flapProgress < 0.5) {
      canvas.drawLine(
        Offset(w * 0.1, h * 0.75),
        Offset(w * 0.4, h * 0.55),
        paint..strokeWidth = 1.5,
      );
      canvas.drawLine(
        Offset(w * 0.9, h * 0.75),
        Offset(w * 0.6, h * 0.55),
        paint..strokeWidth = 1.5,
      );
    }
    canvas.restore();
  }

  @override
  bool shouldRepaint(AnimatedEmailIconPainter old) => true;
}

class AnimatedKeyIconPainter extends CustomPainter {
  final double t;
  AnimatedKeyIconPainter(this.t);
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFF0071E3)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3
      ..strokeCap = StrokeCap.round;
    final w = size.width;
    final h = size.height;
    double rotation = 0;
    if (t >= 0.3 && t < 0.7) {
      double rotT = (t - 0.3) / 0.4;
      rotation = Curves.elasticOut.transform(rotT) * (pi / 2);
    } else if (t >= 0.7) {
      rotation = pi / 2;
    }
    canvas.save();
    canvas.translate(w / 2, h / 2);
    canvas.rotate(rotation);
    canvas.translate(-w / 2, -h / 2);
    final headRect = Rect.fromCircle(
      center: Offset(w * 0.3, h * 0.5),
      radius: w * 0.18,
    );
    canvas.drawOval(headRect, paint);
    canvas.drawCircle(
      Offset(w * 0.3, h * 0.5),
      w * 0.08,
      paint..strokeWidth = 1.5,
    );
    final shaftPath = Path()
      ..moveTo(w * 0.48, h * 0.5)
      ..lineTo(w * 0.85, h * 0.5);
    canvas.drawPath(shaftPath, paint..strokeWidth = 4);
    canvas.drawLine(
      Offset(w * 0.72, h * 0.5),
      Offset(w * 0.72, h * 0.65),
      paint..strokeWidth = 3,
    );
    canvas.drawLine(
      Offset(w * 0.82, h * 0.5),
      Offset(w * 0.82, h * 0.60),
      paint..strokeWidth = 3,
    );
    if (t > 0.6) {
      double shineT = (t - 0.6) / 0.4;
      final shinePaint = Paint()
        ..color = Colors.white.withOpacity((1 - shineT) * 0.8)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 10);
      canvas.drawCircle(
        Offset(w * 0.85, h * 0.5),
        w * 0.1 + (shineT * 10),
        shinePaint,
      );
    }
    canvas.restore();
  }

  @override
  bool shouldRepaint(AnimatedKeyIconPainter old) => true;
}

class AnimatedCheckmarkPainter extends CustomPainter {
  final double progress;
  AnimatedCheckmarkPainter(this.progress);
  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width;
    final h = size.height;
    final circleScale = Curves.elasticOut.transform(
      progress.clamp(0.0, 0.6) / 0.6,
    );
    final circlePaint = Paint()
      ..color = const Color(0xFF00D26A)
      ..style = PaintingStyle.fill;
    canvas.drawCircle(
      Offset(w / 2, h / 2),
      w * 0.45 * circleScale,
      Paint()
        ..color = const Color(0xFF00D26A).withOpacity(0.3)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 20),
    );
    canvas.drawCircle(
      Offset(w / 2, h / 2),
      w * 0.45 * circleScale,
      circlePaint,
    );
    if (progress > 0.2) {
      double checkProgress = (progress - 0.2) / 0.4;
      checkProgress = checkProgress.clamp(0.0, 1.0);
      final checkPath = Path()
        ..moveTo(w * 0.28, h * 0.5)
        ..lineTo(w * 0.42, h * 0.65)
        ..lineTo(w * 0.72, h * 0.35);
      final checkPaint = Paint()
        ..color = Colors.white
        ..style = PaintingStyle.stroke
        ..strokeWidth = 6
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round;
      final pathMetrics = checkPath.computeMetrics().first;
      final extractPath = pathMetrics.extractPath(
        0,
        pathMetrics.length * Curves.easeOutCubic.transform(checkProgress),
      );
      canvas.drawPath(extractPath, checkPaint);
    }
    if (progress > 0.5) {
      final particlePaint = Paint()..color = Colors.white;
      double explosionT = (progress - 0.5) / 0.5;
      explosionT = Curves.decelerate.transform(explosionT.clamp(0.0, 1.0));
      for (int i = 0; i < 12; i++) {
        double angle = (i / 12) * 2 * pi;
        double dist = w * 0.5 + (explosionT * w * 0.4);
        double px = w / 2 + cos(angle) * dist;
        double py = h / 2 + sin(angle) * dist;
        double pSize = (1 - explosionT) * 4;
        canvas.drawCircle(Offset(px, py), pSize, particlePaint);
      }
    }
  }

  @override
  bool shouldRepaint(AnimatedCheckmarkPainter old) => true;
}

// ============ NEW ANIMATED PAINTERS ============

class AnimatedIDCardPainter extends CustomPainter {
  final double t;
  AnimatedIDCardPainter(this.t);
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFF0071E3)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3
      ..strokeCap = StrokeCap.round;
    final w = size.width;
    final h = size.height;
    // Slide in
    double slide =
        (1.0 - Curves.easeOutCubic.transform(t.clamp(0.0, 0.5) * 2)) * 20;
    // Stamp effect
    double stamp = Curves.elasticOut.transform((t - 0.5).clamp(0.0, 0.5) * 2);

    canvas.save();
    canvas.translate(0, slide);
    final card = RRect.fromRectAndRadius(
      Rect.fromLTWH(w * 0.15, h * 0.25, w * 0.7, h * 0.45),
      const Radius.circular(8),
    );
    canvas.drawRRect(card, paint);
    // Photo Box
    if (t > 0.5) {
      final photoCenter = Offset(w * 0.35, h * 0.47);
      canvas.drawCircle(photoCenter, w * 0.12 * stamp, paint);
      // Lines
      final linePaint = Paint()
        ..color = Colors.white.withOpacity(0.5)
        ..strokeWidth = 2
        ..strokeCap = StrokeCap.round;
      if (stamp > 0.5) {
        canvas.drawLine(
          Offset(w * 0.55, h * 0.42),
          Offset(w * 0.75, h * 0.42),
          linePaint,
        );
        canvas.drawLine(
          Offset(w * 0.55, h * 0.52),
          Offset(w * 0.75, h * 0.52),
          linePaint,
        );
      }
    }
    canvas.restore();
  }

  @override
  bool shouldRepaint(AnimatedIDCardPainter old) => true;
}

class AnimatedShieldPainter extends CustomPainter {
  final double t;
  AnimatedShieldPainter(this.t);
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFF0071E3)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3
      ..strokeCap = StrokeCap.round;
    final w = size.width;
    final h = size.height;
    final shieldPath = Path();
    shieldPath.moveTo(w * 0.5, h * 0.2);
    shieldPath.cubicTo(
      w * 0.9,
      h * 0.2,
      w * 0.9,
      h * 0.5,
      w * 0.5,
      h * 0.85,
    ); // Right side
    shieldPath.cubicTo(
      w * 0.1,
      h * 0.5,
      w * 0.1,
      h * 0.2,
      w * 0.5,
      h * 0.2,
    ); // Left side

    // Animate drawing
    if (t < 0.5) {
      final metric = shieldPath.computeMetrics().first;
      final extract = metric.extractPath(0, metric.length * (t * 2));
      canvas.drawPath(extract, paint);
    } else {
      canvas.drawPath(shieldPath, paint);
      // Lock Icon
      double scale = Curves.elasticOut.transform((t - 0.5).clamp(0.0, 0.5) * 2);
      if (scale > 0) {
        canvas.drawCircle(
          Offset(w * 0.5, h * 0.45),
          w * 0.05 * scale,
          Paint()..color = const Color(0xFF0071E3),
        );
        canvas.drawRect(
          Rect.fromCenter(
            center: Offset(w * 0.5, h * 0.55),
            width: w * 0.06 * scale,
            height: h * 0.08 * scale,
          ),
          Paint()..color = const Color(0xFF0071E3),
        );
      }
      // Sheen
      if (t > 0.7) {
        double sheenPos = (t - 0.7) / 0.3; // 0 to 1
        final sheenPaint = Paint()
          ..color = Colors.white.withOpacity(0.4)
          ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 5);
        canvas.save();
        canvas.clipPath(shieldPath);
        canvas.drawCircle(
          Offset(w * (sheenPos * 2 - 0.5), h * 0.5),
          w * 0.3,
          sheenPaint,
        );
        canvas.restore();
      }
    }
  }

  @override
  bool shouldRepaint(AnimatedShieldPainter old) => true;
}
