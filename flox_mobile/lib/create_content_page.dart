import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;
import 'dart:math' as math;
import 'package:flutter/foundation.dart'; // For kIsWeb
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:camera/camera.dart';
import 'package:file_picker/file_picker.dart';
import 'package:image_picker/image_picker.dart';
import 'package:video_player/video_player.dart';
import 'package:http/http.dart' as http;
import 'package:cross_file/cross_file.dart'; // Ensure XFile is available if not via camera
import 'dart:convert';
import 'constants.dart';
import 'session_manager.dart';

const _accent = Color(0xFF0A84FF);
const _accentLight = Color(0xFF5AC8FA);

class CreateContentPage extends StatefulWidget {
  const CreateContentPage({super.key});
  @override
  State<CreateContentPage> createState() => _CreateContentPageState();
}

class _CreateContentPageState extends State<CreateContentPage>
    with TickerProviderStateMixin {
  CameraController? _cameraController;
  List<CameraDescription> _cameras = [];
  bool _isCameraInitialized = false;
  bool _isRecording = false;
  bool _isPaused = false;
  bool _isFrontCamera = true;
  bool _isFlashOn = false;

  int _selectedDurationIndex = 0;
  final List<String> _durationOptions = ['3m', '60s', '15s'];

  int _selectedNavIndex = 2;
  final List<String> _navOptions = ['STREAM', 'PHOTO', 'VIDEO'];

  XFile? _recordedFile;
  bool _isVideo = true;
  bool _isEditing = false;

  Timer? _recordingTimer;
  int _recordingSeconds = 0;
  int _maxRecordingSeconds = 180;

  late AnimationController _pulseController;
  late AnimationController _progressController;
  late AnimationController _fadeController;

  final List<_TextOverlay> _textOverlays = [];
  final List<_EmojiOverlay> _emojiOverlays = [];
  _MusicTrack? _selectedMusic;

  bool _isAddingText = false;
  final _textInputController = TextEditingController();
  Color _currentTextColor = Colors.white;
  bool _currentHasOutline = false;
  bool _currentHasBackground = false;
  Color _currentBgColor = Colors.black54;

  // Filter state
  int _selectedFilterIndex = 0;
  final List<String> _filterNames = [
    'Original',
    'Vivid',
    'Cool',
    'Warm',
    'Mono',
    'B&W',
    'Vintage',
  ];
  final List<List<double>> _filterMatrices = [
    [1, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, 0], // Original
    [1.2, 0, 0, 0, 0, 0, 1.2, 0, 0, 0, 0, 0, 1.2, 0, 0, 0, 0, 0, 1, 0], // Vivid
    [0.9, 0, 0, 0, 0, 0, 0.9, 0, 0, 0, 0, 0, 1.2, 0, 0, 0, 0, 0, 1, 0], // Cool
    [1.2, 0, 0, 0, 0, 0, 1.0, 0, 0, 0, 0, 0, 0.8, 0, 0, 0, 0, 0, 1, 0], // Warm
    [1.5, 0, 0, 0, 0, 1.5, 0, 0, 0, 0, 1.5, 0, 0, 0, 0, 0, 0, 0, 1, 0], // Mono
    [
      0.2,
      0.7,
      0.1,
      0,
      0,
      0.2,
      0.7,
      0.1,
      0,
      0,
      0.2,
      0.7,
      0.1,
      0,
      0,
      0,
      0,
      0,
      1,
      0,
    ], // B&W
    [
      0.9,
      0,
      0,
      0,
      0,
      0,
      0.8,
      0,
      0,
      0,
      0,
      0,
      0.6,
      0,
      0,
      0,
      0,
      0,
      1,
      0,
    ], // Vintage
  ];
  bool _isShowingFilters = false;
  bool _isShowingFiltersPreShot = false;
  bool _isCountdownActive = false;
  int _countdownTimer = 3;
  Timer? _countdownRealTimer;
  int _selectedCountdownIndex = 0; // 0=Off, 1=3s, 2=10s
  final List<int> _timerOptions = [0, 3, 10];

  final _textColors = [
    Colors.white,
    Colors.black,
    Colors.red,

    Colors.yellow,
    Colors.cyan,
    Colors.pink,
    Colors.green,
    Colors.orange,
    Colors.purple,
    _accent,
  ];

  @override
  void initState() {
    super.initState();
    _initCamera();
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1000),
    )..repeat(reverse: true);
    _progressController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 180),
    );
    _fadeController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 400),
      value: 1.0,
    );
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
  }

  void _updateMaxDuration() {
    _maxRecordingSeconds = [180, 60, 15][_selectedDurationIndex];
    _progressController.duration = Duration(seconds: _maxRecordingSeconds);
  }

  Future<void> _initCamera() async {
    _cameras = await availableCameras();
    if (_cameras.isNotEmpty) {
      await _setupCamera(
        _cameras.firstWhere(
          (c) => c.lensDirection == CameraLensDirection.front,
          orElse: () => _cameras.first,
        ),
      );
    }
  }

  Future<void> _setupCamera(CameraDescription camera) async {
    _cameraController?.dispose();
    _cameraController = CameraController(
      camera,
      ResolutionPreset.high,
      enableAudio: true,
    );
    try {
      await _cameraController!.initialize();
      if (mounted) setState(() => _isCameraInitialized = true);
    } catch (e) {
      debugPrint('Camera error: $e');
    }
  }

  void _flipCamera() async {
    if (_cameras.length < 2 || _isRecording) return;
    HapticFeedback.lightImpact();

    // Turn off flash when flipping
    if (_isFlashOn && !_isFrontCamera) {
      try {
        await _cameraController?.setFlashMode(FlashMode.off);
      } catch (_) {}
    }

    setState(() {
      _isCameraInitialized = false;
      _isFlashOn = false;
    });

    _isFrontCamera = !_isFrontCamera;
    await _setupCamera(
      _cameras.firstWhere(
        (c) =>
            c.lensDirection ==
            (_isFrontCamera
                ? CameraLensDirection.front
                : CameraLensDirection.back),
        orElse: () => _cameras.first,
      ),
    );
  }

  void _toggleFlash() async {
    if (_cameraController == null) return;
    HapticFeedback.mediumImpact();

    if (!_isFrontCamera) {
      // Back camera uses physical torch
      try {
        _isFlashOn = !_isFlashOn;
        await _cameraController!.setFlashMode(
          _isFlashOn ? FlashMode.torch : FlashMode.off,
        );
      } catch (e) {
        debugPrint('Flash error: $e');
        _isFlashOn = false;
      }
    } else {
      // Front camera uses UI glow effect
      _isFlashOn = !_isFlashOn;
    }
    setState(() {});
  }

  Future<void> _startRecording() async {
    if (_cameraController == null) return;
    _updateMaxDuration();
    try {
      await _cameraController!.startVideoRecording();
      _fadeController.animateTo(
        0.0,
        duration: const Duration(milliseconds: 300),
      );
      setState(() {
        _isRecording = true;
        _isPaused = false;
        _recordingSeconds = 0;
      });
      _progressController.forward(from: 0);
      _recordingTimer = Timer.periodic(const Duration(seconds: 1), (_) {
        setState(() => _recordingSeconds++);
        if (_recordingSeconds >= _maxRecordingSeconds) _finishRecording();
      });
    } catch (e) {
      debugPrint('Recording error: $e');
    }
  }

  void _togglePause() {
    if (!_isRecording) return;
    HapticFeedback.mediumImpact();
    setState(() => _isPaused = !_isPaused);
    if (_isPaused) {
      _cameraController?.pauseVideoRecording();
      _recordingTimer?.cancel();
      _progressController.stop();
    } else {
      _cameraController?.resumeVideoRecording();
      _progressController.forward();
      _recordingTimer = Timer.periodic(const Duration(seconds: 1), (_) {
        setState(() => _recordingSeconds++);
        if (_recordingSeconds >= _maxRecordingSeconds) _finishRecording();
      });
    }
  }

  Future<void> _finishRecording() async {
    if (_cameraController == null || !_isRecording) return;
    _recordingTimer?.cancel();
    _progressController.stop();
    try {
      final file = await _cameraController!.stopVideoRecording();
      _fadeController.animateTo(1.0);
      setState(() {
        _isRecording = false;
        _isPaused = false;
        _recordedFile = file; // XFile
        _isVideo = true;
        _isEditing = true;
        _textOverlays.clear();
        _emojiOverlays.clear();
      });
    } catch (e) {
      debugPrint('Stop error: $e');
    }
  }

  Future<void> _takePhoto() async {
    if (_cameraController == null) return;
    HapticFeedback.mediumImpact();
    try {
      final file = await _cameraController!.takePicture();
      setState(() {
        _recordedFile = file; // XFile
        _isVideo = false;
        _isEditing = true;
      });
    } catch (e) {
      debugPrint('Photo error: $e');
    }
  }

  Future<void> _pickFromGallery() async {
    if (_isRecording) return;

    final isPhoto = _selectedNavIndex == 1;

    if (!isPhoto) {
      // Use FilePicker for video to allow all extensions
      try {
        final result = await FilePicker.platform.pickFiles(
          type: FileType.video,
          allowMultiple: false,
        );

        if (result != null && result.files.single.path != null) {
          setState(() {
            _recordedFile = XFile(result.files.single.path!);
            _isVideo = true;
            _isEditing = true;
          });
        }
      } catch (e) {
        debugPrint('Error picking video: $e');
        // Fallback or show error
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(SnackBar(content: Text('Error picking video: $e')));
        }
      }
    } else {
      // Keep ImagePicker for photos as it is optimized for images
      final picker = ImagePicker();
      final i = await picker.pickImage(source: ImageSource.gallery);
      if (i != null) {
        setState(() {
          _recordedFile = i; // XFile
          _isVideo = false;
          _isEditing = true;
        });
      }
    }
  }

  void _onShootTap() {
    HapticFeedback.mediumImpact();
    if (_selectedNavIndex == 1) {
      _takePhoto();
    } else if (_selectedNavIndex == 2) {
      if (_isRecording) {
        _togglePause();
      } else {
        if (_timerOptions[_selectedCountdownIndex] > 0) {
          _startCountdown();
        } else {
          _startRecording();
        }
      }
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Live streaming coming soon! 📡'),
          backgroundColor: _accent,
        ),
      );
    }
  }

  void _startCountdown() {
    setState(() {
      _isCountdownActive = true;
      _countdownTimer = _timerOptions[_selectedCountdownIndex];
    });

    _countdownRealTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_countdownTimer > 1) {
        setState(() => _countdownTimer--);
      } else {
        _countdownRealTimer?.cancel();
        setState(() {
          _isCountdownActive = false;
        });
        _startRecording();
      }
    });
  }

  Future<void> _discardRecording() async {
    if (_cameraController == null) return;
    HapticFeedback.heavyImpact();

    try {
      if (_isRecording) {
        await _cameraController!.stopVideoRecording();
      }
      _recordingTimer?.cancel();
      _progressController.reset();
      setState(() {
        _isRecording = false;
        _isPaused = false;
        _recordingSeconds = 0;
        _recordedFile = null;
      });
      _fadeController.animateTo(1.0);
    } catch (e) {
      debugPrint('Discard error: $e');
    }
  }

  void _openMusicPicker() => Navigator.push(
    context,
    PageRouteBuilder(
      pageBuilder: (_, _, _) => _MusicLibraryPage(
        onSelect: (t) => setState(() => _selectedMusic = t),
      ),
      transitionsBuilder: (_, a, _, c) => SlideTransition(
        position: Tween(
          begin: const Offset(0, 1),
          end: Offset.zero,
        ).animate(CurvedAnimation(parent: a, curve: Curves.easeOutCubic)),
        child: c,
      ),
    ),
  );
  void _cancelEditing() {
    _fadeController.animateTo(1.0);
    setState(() {
      _recordedFile = null;
      _isEditing = false;
      _isAddingText = false;
      _textOverlays.clear();
      _emojiOverlays.clear();
      _selectedMusic = null;
    });
  }

  void _goToPublish() => Navigator.push(
    context,
    PageRouteBuilder(
      pageBuilder: (_, _, _) => PublishContentPage(
        file: _recordedFile,
        isVideo: _isVideo,
        selectedMusic: _selectedMusic,
      ),
      transitionsBuilder: (_, a, _, c) => FadeTransition(opacity: a, child: c),
    ),
  );

  void _showTextInput() => setState(() {
    _isAddingText = true;
    _textInputController.clear();
  });
  void _confirmText() {
    if (_textInputController.text.isNotEmpty) {
      setState(
        () => _textOverlays.add(
          _TextOverlay(
            text: _textInputController.text,
            position: const Offset(100, 300),
            color: _currentTextColor,
            hasOutline: _currentHasOutline,
            hasBackground: _currentHasBackground,
            backgroundColor: _currentBgColor,
          ),
        ),
      );
    }
    setState(() => _isAddingText = false);
  }

  void _addEmoji() {
    final emojis = [
      '😀',
      '😍',
      '🔥',
      '💯',
      '✨',
      '💀',
      '😭',
      '🥺',
      '💕',
      '🎉',
      '👀',
      '🤣',
      '😎',
      '🥳',
      '💪',
      '🙌',
      '❤️',
      '💔',
      '🌟',
      '⚡',
    ];
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1A1A25),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.white24,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 20),
            const Text(
              'Add Emoji',
              style: TextStyle(
                color: Colors.white,
                fontSize: 20,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 24),
            SizedBox(
              height: 300,
              child: GridView.count(
                crossAxisCount: 5,
                children: emojis
                    .map(
                      (e) => GestureDetector(
                        onTap: () {
                          setState(
                            () => _emojiOverlays.add(
                              _EmojiOverlay(
                                emoji: e,
                                position: Offset(
                                  100 + math.Random().nextDouble() * 100,
                                  200,
                                ),
                              ),
                            ),
                          );
                          Navigator.pop(ctx);
                        },
                        child: Center(
                          child: Text(e, style: const TextStyle(fontSize: 40)),
                        ),
                      ),
                    )
                    .toList(),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _openStickerPicker() {
    final stickers = [
      Icons.star,
      Icons.favorite,
      Icons.bolt,
      Icons.rocket_launch,
      Icons.local_fire_department,
      Icons.celebration,
      Icons.auto_awesome,
      Icons.pets,
      Icons.face,
      Icons.music_note,
      Icons.camera_alt,
      Icons.videogame_asset,
    ];
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1A1A25),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.white24,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 20),
            const Text(
              'Stickers',
              style: TextStyle(
                color: Colors.white,
                fontSize: 20,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 16),
            TextField(
              style: const TextStyle(color: Colors.white),
              decoration: InputDecoration(
                hintText: 'Search stickers...',
                hintStyle: const TextStyle(color: Colors.white38),
                prefixIcon: const Icon(Icons.search, color: Colors.white38),
                filled: true,
                fillColor: Colors.white.withOpacity(0.08),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: BorderSide.none,
                ),
              ),
            ),
            const SizedBox(height: 24),
            SizedBox(
              height: 300,
              child: GridView.count(
                crossAxisCount: 4,
                mainAxisSpacing: 16,
                crossAxisSpacing: 16,
                children: stickers
                    .map(
                      (s) => GestureDetector(
                        onTap: () {
                          setState(
                            () => _emojiOverlays.add(
                              _EmojiOverlay(
                                emoji: '',
                                icon: s,
                                position: Offset(
                                  100 + math.Random().nextDouble() * 100,
                                  200,
                                ),
                              ),
                            ),
                          );
                          Navigator.pop(context);
                        },
                        child: Container(
                          decoration: BoxDecoration(
                            color: Colors.white.withOpacity(0.05),
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child: Icon(s, color: _accent, size: 32),
                        ),
                      ),
                    )
                    .toList(),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  void dispose() {
    _cameraController?.dispose();
    _recordingTimer?.cancel();
    _pulseController.dispose();
    _progressController.dispose();
    _fadeController.dispose();
    _textInputController.dispose();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => _isEditing && _recordedFile != null
      ? _buildEditingScreen()
      : _buildCameraScreen();

  Widget _buildCameraScreen() {
    final size = MediaQuery.of(context).size;
    final bottomPadding = MediaQuery.of(context).padding.bottom;
    final isPhoto = _selectedNavIndex == 1;
    final isVideo = _selectedNavIndex == 2;
    // Define a lighter accent variant directly
    final Color accentLight = _accent.withOpacity(0.7);

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // 1. Camera Preview (Full Immersive)
          Positioned.fill(
            child: Stack(
              fit: StackFit.expand,
              children: [
                _isCameraInitialized && _cameraController != null
                    ? (kIsWeb
                          ? CameraPreview(_cameraController!)
                          : ClipRRect(
                              borderRadius: BorderRadius.circular(24),
                              child: CameraPreview(_cameraController!),
                            ))
                    : Container(color: const Color(0xFF121212)),

                // Color Filters Applied
                if (_selectedFilterIndex > 0)
                  Positioned.fill(
                    child: ColorFiltered(
                      colorFilter: ColorFilter.matrix(
                        _filterMatrices[_selectedFilterIndex],
                      ),
                      child: Container(color: Colors.transparent),
                    ),
                  ),

                // Flash Internal Layer
                if (_isFrontCamera && _isFlashOn && !_isEditing) ...[
                  const _ScreenFlashGlow(),
                  const _ScreenFlashGlow(),
                ],

                // Gradient Vignettes for Legibility (Top & Bottom)
                Positioned(
                  top: 0,
                  left: 0,
                  right: 0,
                  height: 160,
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [
                          Colors.black.withOpacity(0.6),
                          Colors.transparent,
                        ],
                      ),
                    ),
                  ),
                ),
                Positioned(
                  bottom: 0,
                  left: 0,
                  right: 0,
                  height: 240,
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.bottomCenter,
                        end: Alignment.topCenter,
                        colors: [
                          Colors.black.withOpacity(0.7),
                          Colors.transparent,
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // 2. Top Controls (Minimal, Glass)
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: AnimatedOpacity(
              opacity: _isRecording ? 0.0 : 1.0,
              duration: const Duration(milliseconds: 300),
              curve: Curves.easeOut,
              child: SafeArea(
                bottom: false,
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 20,
                    vertical: 16,
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      // Close (Discrete)
                      _PremiumIconButton(
                        icon: Icons.close,
                        onTap: () => Navigator.pop(context),
                      ),

                      const Spacer(),

                      // Right Cluster (Sound, Flash, Flip)
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          _PremiumIconButton(
                            icon: Icons.music_note_rounded,
                            isActive: _selectedMusic != null,
                            onTap: _openMusicPicker,
                          ),
                          const SizedBox(width: 16),
                          _PremiumIconButton(
                            icon: _isFlashOn
                                ? Icons.flash_on_rounded
                                : Icons.flash_off_rounded,
                            isActive: _isFlashOn,
                            onTap: _toggleFlash,
                          ),
                          const SizedBox(width: 16),
                          _PremiumIconButton(
                            icon: Icons.flip_camera_ios_rounded,
                            onTap: _flipCamera,
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),

          // 3. REC Indicator (Precise)
          if (_isRecording)
            Positioned(
              top: MediaQuery.of(context).padding.top + 20,
              left: 0,
              right: 0,
              child: Center(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(16),
                  child: BackdropFilter(
                    filter: ui.ImageFilter.blur(sigmaX: 8, sigmaY: 8),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 6,
                      ),
                      color: const Color(
                        0xFFFF3B30,
                      ).withOpacity(0.8), // Premium Red
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            width: 6,
                            height: 6,
                            decoration: const BoxDecoration(
                              color: Colors.white,
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            '${(_recordingSeconds ~/ 60).toString().padLeft(2, '0')}:${(_recordingSeconds % 60).toString().padLeft(2, '0')}',
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w600,
                              fontSize: 14,
                              fontFeatures: [ui.FontFeature.tabularFigures()],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),

          // Side Tools (Timer, Filters)
          if (!_isRecording && !_isCountdownActive)
            Positioned(
              right: 20,
              top: MediaQuery.of(context).padding.top + 100,
              child: Column(
                children: [
                  _PremiumIconButton(
                    icon: _timerOptions[_selectedCountdownIndex] == 0
                        ? Icons.timer_outlined
                        : Icons.timer,
                    isActive: _timerOptions[_selectedCountdownIndex] > 0,
                    onTap: () {
                      HapticFeedback.selectionClick();
                      setState(() {
                        _selectedCountdownIndex =
                            (_selectedCountdownIndex + 1) %
                            _timerOptions.length;
                      });
                    },
                  ),
                  const SizedBox(height: 20),
                  _PremiumIconButton(
                    icon: Icons.filter_vintage_outlined,
                    isActive: _isShowingFiltersPreShot,
                    onTap: () {
                      HapticFeedback.selectionClick();
                      setState(
                        () => _isShowingFiltersPreShot =
                            !_isShowingFiltersPreShot,
                      );
                    },
                  ),
                ],
              ),
            ),

          // Countdown Overlay
          if (_isCountdownActive)
            Positioned.fill(
              child: Container(
                color: Colors.black26,
                child: Center(
                  child: Text(
                    '$_countdownTimer',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 120,
                      fontWeight: FontWeight.bold,
                      shadows: [Shadow(blurRadius: 20, color: Colors.black45)],
                    ),
                  ),
                ),
              ),
            ),

          // 4. Integrated Duration Control (Anchored)
          if (!_isRecording && isVideo)
            Positioned(
              bottom: bottomPadding + 190,
              left: 0,
              right: 0,
              child: Center(
                child: Container(
                  padding: const EdgeInsets.all(4),
                  decoration: BoxDecoration(
                    color: Colors.black.withOpacity(0.3),
                    borderRadius: BorderRadius.circular(100),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: _durationOptions.asMap().entries.map((e) {
                      final isSelected = _selectedDurationIndex == e.key;
                      return GestureDetector(
                        onTap: () {
                          HapticFeedback.selectionClick();
                          setState(() => _selectedDurationIndex = e.key);
                        },
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          curve: Curves.easeOut,
                          padding: const EdgeInsets.symmetric(
                            horizontal: 14,
                            vertical: 8,
                          ),
                          decoration: BoxDecoration(
                            color: isSelected
                                ? Colors.white.withOpacity(0.15)
                                : Colors.transparent,
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            e.value,
                            style: TextStyle(
                              color: isSelected
                                  ? Colors.white
                                  : Colors.white.withOpacity(0.4),
                              fontWeight: isSelected
                                  ? FontWeight.w600
                                  : FontWeight.w400,
                              fontSize: 12,
                              letterSpacing: 0.5,
                            ),
                          ),
                        ),
                      );
                    }).toList(),
                  ),
                ),
              ),
            ),

          // 5. Bottom Control Area (Record, Gallery, Modes)
          Positioned(
            bottom: bottomPadding + 20,
            left: 0,
            right: 0,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Primary Action Row
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 40),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      // Discard Button (Left of Record)
                      if (_isRecording || _isPaused)
                        _PremiumSideButton(
                          icon: Icons.close_rounded,
                          label: 'Discard',
                          isVisible: true,
                          onTap: _discardRecording,
                        )
                      else
                        // Upload (Minimal)
                        _PremiumSideButton(
                          icon: Icons.photo_library_outlined,
                          label: 'Upload',
                          isVisible: true,
                          onTap: _pickFromGallery,
                        ),

                      // Shutter (Premium Material)
                      GestureDetector(
                        onTap: _onShootTap,
                        child: AnimatedBuilder(
                          animation: _pulseController,
                          builder: (_, _) {
                            final scale = _isRecording && !_isPaused
                                ? 1.0 + _pulseController.value * 0.03
                                : 1.0;
                            return Transform.scale(
                              scale: scale,
                              child: Container(
                                width: 80,
                                height: 80,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  border: Border.all(
                                    color: Colors.white.withOpacity(0.2),
                                    width: 4,
                                  ),
                                  boxShadow: [
                                    BoxShadow(
                                      color: Colors.black.withOpacity(0.2),
                                      blurRadius: 10,
                                      spreadRadius: 2,
                                    ),
                                  ],
                                ),
                                child: Padding(
                                  padding: const EdgeInsets.all(4),
                                  child: AnimatedContainer(
                                    duration: const Duration(milliseconds: 300),
                                    decoration: BoxDecoration(
                                      shape: BoxShape.circle,
                                      color: _isRecording
                                          ? const Color(
                                              0xFFFF3B30,
                                            ) // Recording Red
                                          : isPhoto
                                          ? Colors.white
                                          : _accent, // Brand Blue or White
                                      gradient: _isRecording
                                          ? null
                                          : LinearGradient(
                                              begin: Alignment.topLeft,
                                              end: Alignment.bottomRight,
                                              colors: isPhoto
                                                  ? [
                                                      Colors.white,
                                                      const Color(0xFFE0E0E0),
                                                    ]
                                                  : [accentLight, _accent],
                                            ),
                                      boxShadow: _isRecording
                                          ? [
                                              BoxShadow(
                                                color: const Color(
                                                  0xFFFF3B30,
                                                ).withOpacity(0.4),
                                                blurRadius: 12,
                                              ),
                                            ]
                                          : null,
                                    ),
                                    child: _isRecording
                                        ? Center(
                                            child: Container(
                                              width: 24,
                                              height: 24,
                                              decoration: BoxDecoration(
                                                color: Colors.white,
                                                borderRadius:
                                                    BorderRadius.circular(4),
                                              ),
                                            ),
                                          )
                                        : null,
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                      ),

                      // Done (Minimal)
                      _PremiumSideButton(
                        icon: Icons.check_rounded,
                        label: 'Done',
                        isVisible: _isRecording || _recordedFile != null,
                        color: _accent,
                        onTap: _isRecording ? _finishRecording : _goToPublish,
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 24),

                // Pre-shot Filter Selector
                if (_isShowingFiltersPreShot && !_isRecording)
                  Container(
                    height: 90,
                    margin: const EdgeInsets.only(bottom: 20),
                    child: ListView.builder(
                      scrollDirection: Axis.horizontal,
                      padding: const EdgeInsets.symmetric(horizontal: 20),
                      itemCount: _filterNames.length,
                      itemBuilder: (context, index) {
                        final isSelected = _selectedFilterIndex == index;
                        return GestureDetector(
                          onTap: () {
                            HapticFeedback.selectionClick();
                            setState(() => _selectedFilterIndex = index);
                          },
                          child: Column(
                            children: [
                              Container(
                                width: 54,
                                height: 54,
                                margin: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                ),
                                decoration: BoxDecoration(
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(
                                    color: isSelected
                                        ? _accent
                                        : Colors.white24,
                                    width: 2,
                                  ),
                                ),
                                child: ClipRRect(
                                  borderRadius: BorderRadius.circular(10),
                                  child: ColorFiltered(
                                    colorFilter: ColorFilter.matrix(
                                      _filterMatrices[index],
                                    ),
                                    child: Container(color: Colors.white24),
                                  ),
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                _filterNames[index],
                                style: TextStyle(
                                  color: isSelected
                                      ? Colors.white
                                      : Colors.white54,
                                  fontSize: 10,
                                  fontWeight: isSelected
                                      ? FontWeight.w600
                                      : FontWeight.w400,
                                ),
                              ),
                            ],
                          ),
                        );
                      },
                    ),
                  ),

                // Mode Selector (Clean Typography with Sliding Underline)
                AnimatedOpacity(
                  opacity: _isRecording ? 0.0 : 1.0,
                  duration: const Duration(milliseconds: 200),
                  child: SizedBox(
                    key: const ValueKey('mode_selector_container'),
                    width: 270, // Slightly wider
                    height: 44,
                    child: Stack(
                      children: [
                        // Sliding Underline (Enhanced)
                        AnimatedPositioned(
                          duration: const Duration(milliseconds: 400),
                          curve: Curves.easeOutBack,
                          left: _selectedNavIndex * 90.0 + 30.0,
                          bottom: 2,
                          child: Container(
                            width: 30,
                            height: 2.5,
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(2),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.white.withOpacity(0.3),
                                  blurRadius: 4,
                                ),
                              ],
                            ),
                          ),
                        ),
                        // Mode Tabs
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: _navOptions.asMap().entries.map((e) {
                            final isSelected = e.key == _selectedNavIndex;
                            return GestureDetector(
                              onTap: () {
                                HapticFeedback.selectionClick();
                                setState(() {
                                  _selectedNavIndex = e.key;
                                  _isVideo = e.key == 0 || e.key == 2;
                                });
                              },
                              child: Container(
                                width: 90,
                                alignment: Alignment.center,
                                color: Colors.transparent,
                                child: Text(
                                  e.value,
                                  style: TextStyle(
                                    color: isSelected
                                        ? Colors.white
                                        : Colors.white.withOpacity(0.4),
                                    fontWeight: isSelected
                                        ? FontWeight.w700
                                        : FontWeight.w500,
                                    fontSize: 12,
                                    letterSpacing: 1.0,
                                  ),
                                ),
                              ),
                            );
                          }).toList(),
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

  Widget _buildEditingScreen() {
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // Media with Filter
          ClipRRect(
            borderRadius: BorderRadius.circular(24),
            child: ColorFiltered(
              colorFilter: ColorFilter.matrix(
                _filterMatrices[_selectedFilterIndex],
              ),
              child: _isVideo
                  ? _VideoPreview(file: _recordedFile!)
                  : (kIsWeb
                        ? Image.network(_recordedFile!.path, fit: BoxFit.cover)
                        : Image.file(
                            File(_recordedFile!.path),
                            fit: BoxFit.cover,
                          )),
            ),
          ),

          ..._textOverlays.map(
            (t) => _TransformableOverlay(
              key: ValueKey(t.hashCode),
              initialPosition: t.position,
              initialScale: t.scale,
              initialRotation: t.rotation,
              onUpdate: (p, s, r) => setState(() {
                t.position = p;
                t.scale = s;
                t.rotation = r;
              }),
              onDelete: () => setState(() => _textOverlays.remove(t)),
              child: Container(
                padding: t.hasBackground
                    ? const EdgeInsets.symmetric(horizontal: 16, vertical: 8)
                    : EdgeInsets.zero,
                decoration: t.hasBackground
                    ? BoxDecoration(
                        color: t.backgroundColor,
                        borderRadius: BorderRadius.circular(16),
                      )
                    : null,
                child: Text(
                  t.text,
                  style: TextStyle(
                    color: t.color,
                    fontSize: 28,
                    fontWeight: FontWeight.bold,
                    shadows: t.hasOutline
                        ? [
                            const Shadow(
                              offset: Offset(-1.5, -1.5),
                              color: Colors.black,
                            ),
                            const Shadow(
                              offset: Offset(1.5, 1.5),
                              color: Colors.black,
                            ),
                          ]
                        : [const Shadow(blurRadius: 8, color: Colors.black)],
                  ),
                ),
              ),
            ),
          ),

          ..._emojiOverlays.map(
            (e) => _TransformableOverlay(
              key: ValueKey(e.hashCode),
              initialPosition: e.position,
              initialScale: e.scale,
              initialRotation: e.rotation,
              onUpdate: (p, s, r) => setState(() {
                e.position = p;
                e.scale = s;
                e.rotation = r;
              }),
              onDelete: () => setState(() => _emojiOverlays.remove(e)),
              child: e.icon != null
                  ? Icon(e.icon, color: _accent, size: 64)
                  : Text(e.emoji, style: const TextStyle(fontSize: 48)),
            ),
          ),

          if (_isAddingText)
            _TextInputOverlay(
              controller: _textInputController,
              textColor: _currentTextColor,
              hasOutline: _currentHasOutline,
              hasBackground: _currentHasBackground,
              bgColor: _currentBgColor,
              colors: _textColors,
              onColorChanged: (c) => setState(() => _currentTextColor = c),
              onOutlineToggle: () =>
                  setState(() => _currentHasOutline = !_currentHasOutline),
              onBgToggle: () => setState(
                () => _currentHasBackground = !_currentHasBackground,
              ),
              onBgColorChanged: (c) => setState(() => _currentBgColor = c),
              onCancel: () => setState(() => _isAddingText = false),
              onConfirm: _confirmText,
            ),

          if (!_isAddingText) ...[
            Positioned(
              top: 0,
              left: 0,
              right: 0,
              child: SafeArea(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      _AnimatedIconButton(
                        icon: Icons.close,
                        onTap: _cancelEditing,
                      ),
                      _AnimatedIconButton(
                        icon: Icons.download_rounded,
                        onTap: () {},
                      ),
                    ],
                  ),
                ),
              ),
            ),
            if (_selectedMusic != null)
              Positioned(
                top: 80,
                left: 20,
                right: 20,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 10,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.black54,
                    borderRadius: BorderRadius.circular(24),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.music_note, color: _accent, size: 20),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          '${_selectedMusic!.title} - ${_selectedMusic!.artist}',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 13,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      GestureDetector(
                        onTap: () => setState(() => _selectedMusic = null),
                        child: const Icon(
                          Icons.close,
                          color: Colors.white54,
                          size: 18,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            Positioned(
              right: 12,
              top: 0,
              bottom: 100,
              child: SafeArea(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    _EditToolBtn(
                      icon: Icons.text_fields_rounded,
                      label: 'Text',
                      onTap: _showTextInput,
                    ),
                    const SizedBox(height: 20),
                    _EditToolBtn(
                      icon: Icons.emoji_emotions_rounded,
                      label: 'Emoji',
                      onTap: _addEmoji,
                    ),
                    const SizedBox(height: 20),
                    _EditToolBtn(
                      icon: Icons.sticky_note_2_rounded,
                      label: 'Stickers',
                      onTap: _openStickerPicker,
                    ),
                    const SizedBox(height: 20),
                    _EditToolBtn(
                      icon: Icons.music_note_rounded,
                      label: 'Music',
                      onTap: _openMusicPicker,
                    ),
                    const SizedBox(height: 20),
                    _EditToolBtn(
                      icon: Icons.tune_rounded,
                      label: 'Edit',
                      onTap: () => setState(
                        () => _isShowingFilters = !_isShowingFilters,
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // Filter bar
            if (_isShowingFilters)
              Positioned(
                bottom: 100,
                left: 0,
                right: 0,
                child: SizedBox(
                  height: 100,
                  child: ListView.builder(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    itemCount: _filterNames.length,
                    itemBuilder: (context, index) {
                      final isSelected = _selectedFilterIndex == index;
                      return GestureDetector(
                        onTap: () =>
                            setState(() => _selectedFilterIndex = index),
                        child: Column(
                          children: [
                            AnimatedContainer(
                              duration: const Duration(milliseconds: 200),
                              width: 60,
                              height: 60,
                              margin: const EdgeInsets.symmetric(horizontal: 8),
                              decoration: BoxDecoration(
                                color: isSelected ? _accent : Colors.white12,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(
                                  color: isSelected
                                      ? Colors.white
                                      : Colors.transparent,
                                  width: 2,
                                ),
                              ),
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(10),
                                child: ColorFiltered(
                                  colorFilter: ColorFilter.matrix(
                                    _filterMatrices[index],
                                  ),
                                  child: _isVideo
                                      ? const Icon(
                                          Icons.play_arrow,
                                          color: Colors.white24,
                                        )
                                      : (_recordedFile != null
                                            ? (kIsWeb
                                                  ? Image.network(
                                                      _recordedFile!.path,
                                                      fit: BoxFit.cover,
                                                    )
                                                  : Image.file(
                                                      File(_recordedFile!.path),
                                                      fit: BoxFit.cover,
                                                    ))
                                            : const SizedBox()),
                                ),
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              _filterNames[index],
                              style: TextStyle(
                                color: isSelected
                                    ? Colors.white
                                    : Colors.white54,
                                fontSize: 10,
                                fontWeight: isSelected
                                    ? FontWeight.bold
                                    : FontWeight.normal,
                              ),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
              ),

            Positioned(
              bottom: 24,
              left: 20,
              right: 20,
              child: SafeArea(
                top: false,
                child: Row(
                  children: [
                    Expanded(
                      child: _AnimatedButton(
                        label: 'Cancel',
                        onTap: _cancelEditing,
                        isOutlined: true,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      flex: 2,
                      child: _AnimatedButton(
                        label: 'Next',
                        onTap: _goToPublish,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

// Swipeable Duration Selector Widget
class _SwipeableDurationSelector extends StatefulWidget {
  final List<String> options;
  final int selectedIndex;
  final Function(int) onChanged;

  const _SwipeableDurationSelector({
    required this.options,
    required this.selectedIndex,
    required this.onChanged,
  });

  @override
  State<_SwipeableDurationSelector> createState() =>
      _SwipeableDurationSelectorState();
}

class _SwipeableDurationSelectorState
    extends State<_SwipeableDurationSelector> {
  late int _currentIndex;
  double _dragOffset = 0;
  bool _isDragging = false;
  final double _itemWidth = 60;

  @override
  void initState() {
    super.initState();
    _currentIndex = widget.selectedIndex;
  }

  @override
  void didUpdateWidget(_SwipeableDurationSelector oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.selectedIndex != widget.selectedIndex) {
      _currentIndex = widget.selectedIndex;
    }
  }

  void _onPanStart(DragStartDetails details) {
    HapticFeedback.lightImpact();
    setState(() => _isDragging = true);
  }

  void _onPanUpdate(DragUpdateDetails details) {
    setState(() => _dragOffset += details.delta.dx);
  }

  void _onPanEnd(DragEndDetails details) {
    // Calculate which option to snap to
    final itemsMoved = (_dragOffset / _itemWidth).round();
    int newIndex = (_currentIndex - itemsMoved).clamp(
      0,
      widget.options.length - 1,
    );

    if (newIndex != _currentIndex) {
      HapticFeedback.selectionClick();
      widget.onChanged(newIndex);
    }

    setState(() {
      _currentIndex = newIndex;
      _dragOffset = 0;
      _isDragging = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final double totalWidth = _itemWidth * widget.options.length;
    final double centerOffset =
        (MediaQuery.of(context).size.width / 2) - (totalWidth / 2);

    return SizedBox(
      height: 50,
      child: Stack(
        alignment: Alignment.center,
        children: [
          // Background options (static)
          Row(
            mainAxisSize: MainAxisSize.min,
            children: widget.options.asMap().entries.map((e) {
              return Container(
                width: _itemWidth,
                alignment: Alignment.center,
                child: Text(
                  e.value,
                  style: const TextStyle(
                    color: Colors.white38,
                    fontWeight: FontWeight.w500,
                    fontSize: 14,
                  ),
                ),
              );
            }).toList(),
          ),

          // Swipeable selection box
          AnimatedPositioned(
            duration: _isDragging
                ? Duration.zero
                : const Duration(milliseconds: 250),
            curve: Curves.easeOutCubic,
            left: centerOffset + (_currentIndex * _itemWidth) + _dragOffset,
            child: GestureDetector(
              onPanStart: (details) {
                HapticFeedback.lightImpact();
                setState(() => _isDragging = true);
              },
              onPanUpdate: (details) {
                setState(() => _dragOffset += details.delta.dx);
              },
              onPanEnd: (details) {
                // Calculate which option the box is closest to
                final double currentX =
                    (_currentIndex * _itemWidth) + _dragOffset;
                final int newIndex = (currentX / _itemWidth).round().clamp(
                  0,
                  widget.options.length - 1,
                );

                if (newIndex != _currentIndex) {
                  HapticFeedback.selectionClick();
                  widget.onChanged(newIndex);
                }

                setState(() {
                  _currentIndex = newIndex;
                  _dragOffset = 0;
                  _isDragging = false;
                });
              },
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 150),
                width: _itemWidth,
                height: _isDragging ? 42 : 36,
                decoration: BoxDecoration(
                  color: _accent.withOpacity(0.35),
                  borderRadius: BorderRadius.circular(_isDragging ? 21 : 18),
                  border: Border.all(color: _accent.withOpacity(0.8), width: 2),
                  boxShadow: [
                    BoxShadow(
                      color: _accent.withOpacity(_isDragging ? 0.4 : 0.2),
                      blurRadius: _isDragging ? 16 : 8,
                      spreadRadius: _isDragging ? 2 : 0,
                    ),
                  ],
                ),
                alignment: Alignment.center,
                child: Text(
                  widget.options[_currentIndex],
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AnimatedIconButton extends StatefulWidget {
  final IconData icon;
  final VoidCallback onTap;
  const _AnimatedIconButton({required this.icon, required this.onTap});
  @override
  State<_AnimatedIconButton> createState() => _AnimatedIconButtonState();
}

class _AnimatedIconButtonState extends State<_AnimatedIconButton> {
  double _scale = 1.0;
  @override
  Widget build(BuildContext context) => GestureDetector(
    onTapDown: (_) => setState(() => _scale = 0.9),
    onTapUp: (_) {
      setState(() => _scale = 1.0);
      widget.onTap();
    },
    onTapCancel: () => setState(() => _scale = 1.0),
    child: AnimatedScale(
      scale: _scale,
      duration: const Duration(milliseconds: 100),
      child: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: Colors.black26,
          shape: BoxShape.circle,
          border: Border.all(color: Colors.white12),
        ),
        child: Icon(widget.icon, color: Colors.white, size: 24),
      ),
    ),
  );
}

class _AnimatedButton extends StatefulWidget {
  final String label;
  final VoidCallback onTap;
  final bool isOutlined;
  const _AnimatedButton({
    required this.label,
    required this.onTap,
    this.isOutlined = false,
  });
  @override
  State<_AnimatedButton> createState() => _AnimatedButtonState();
}

class _AnimatedButtonState extends State<_AnimatedButton> {
  double _scale = 1.0;
  @override
  Widget build(BuildContext context) => GestureDetector(
    onTapDown: (_) => setState(() => _scale = 0.95),
    onTapUp: (_) {
      setState(() => _scale = 1.0);
      widget.onTap();
    },
    onTapCancel: () => setState(() => _scale = 1.0),
    child: AnimatedScale(
      scale: _scale,
      duration: const Duration(milliseconds: 100),
      child: Container(
        height: 52,
        decoration: BoxDecoration(
          color: widget.isOutlined ? Colors.transparent : _accent,
          borderRadius: BorderRadius.circular(26),
          border: widget.isOutlined
              ? Border.all(color: Colors.white24, width: 1.5)
              : null,
          boxShadow: widget.isOutlined
              ? null
              : [
                  BoxShadow(
                    color: _accent.withOpacity(0.3),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ],
        ),
        child: Center(
          child: Text(
            widget.label,
            style: TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: widget.isOutlined ? FontWeight.w500 : FontWeight.bold,
            ),
          ),
        ),
      ),
    ),
  );
}

class _EditToolBtn extends StatefulWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  const _EditToolBtn({
    required this.icon,
    required this.label,
    required this.onTap,
  });
  @override
  State<_EditToolBtn> createState() => _EditToolBtnState();
}

class _EditToolBtnState extends State<_EditToolBtn> {
  double _scale = 1.0;
  @override
  Widget build(BuildContext context) => GestureDetector(
    onTapDown: (_) => setState(() => _scale = 0.9),
    onTapUp: (_) {
      setState(() => _scale = 1.0);
      widget.onTap();
    },
    onTapCancel: () => setState(() => _scale = 1.0),
    child: AnimatedScale(
      scale: _scale,
      duration: const Duration(milliseconds: 100),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: Colors.white10,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Colors.white12),
            ),
            child: Icon(widget.icon, color: Colors.white, size: 24),
          ),
          const SizedBox(height: 6),
          Text(
            widget.label,
            style: const TextStyle(color: Colors.white70, fontSize: 11),
          ),
        ],
      ),
    ),
  );
}

class _TextInputOverlay extends StatelessWidget {
  final TextEditingController controller;
  final Color textColor;
  final bool hasOutline;
  final bool hasBackground;
  final Color bgColor;
  final List<Color> colors;
  final Function(Color) onColorChanged;
  final VoidCallback onOutlineToggle;
  final VoidCallback onBgToggle;
  final Function(Color) onBgColorChanged;
  final VoidCallback onCancel;
  final VoidCallback onConfirm;
  const _TextInputOverlay({
    required this.controller,
    required this.textColor,
    required this.hasOutline,
    required this.hasBackground,
    required this.bgColor,
    required this.colors,
    required this.onColorChanged,
    required this.onOutlineToggle,
    required this.onBgToggle,
    required this.onBgColorChanged,
    required this.onCancel,
    required this.onConfirm,
  });
  @override
  Widget build(BuildContext context) => Container(
    color: Colors.black87,
    child: SafeArea(
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                GestureDetector(
                  onTap: onCancel,
                  child: const Text(
                    'Cancel',
                    style: TextStyle(color: Colors.white70, fontSize: 16),
                  ),
                ),
                GestureDetector(
                  onTap: onConfirm,
                  child: const Text(
                    'Done',
                    style: TextStyle(
                      color: _accent,
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: Center(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 40),
                child: Container(
                  padding: hasBackground
                      ? const EdgeInsets.symmetric(horizontal: 20, vertical: 12)
                      : EdgeInsets.zero,
                  decoration: hasBackground
                      ? BoxDecoration(
                          color: bgColor,
                          borderRadius: BorderRadius.circular(16),
                        )
                      : null,
                  child: TextField(
                    controller: controller,
                    style: TextStyle(
                      color: textColor,
                      fontSize: 32,
                      fontWeight: FontWeight.bold,
                    ),
                    textAlign: TextAlign.center,
                    maxLines: 3,
                    autofocus: true,
                    decoration: const InputDecoration(
                      hintText: 'Type here...',
                      hintStyle: TextStyle(color: Colors.white24),
                      border: InputBorder.none,
                    ),
                  ),
                ),
              ),
            ),
          ),
          Container(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: colors
                        .map(
                          (c) => GestureDetector(
                            onTap: () => onColorChanged(c),
                            child: AnimatedContainer(
                              duration: const Duration(milliseconds: 200),
                              width: 36,
                              height: 36,
                              margin: const EdgeInsets.only(right: 10),
                              decoration: BoxDecoration(
                                color: c,
                                shape: BoxShape.circle,
                                border: Border.all(
                                  color: textColor == c
                                      ? _accent
                                      : Colors.white24,
                                  width: textColor == c ? 3 : 1,
                                ),
                              ),
                            ),
                          ),
                        )
                        .toList(),
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: [
                    _ToggleChip(
                      label: 'Outline',
                      isActive: hasOutline,
                      onTap: onOutlineToggle,
                    ),
                    _ToggleChip(
                      label: 'Background',
                      isActive: hasBackground,
                      onTap: onBgToggle,
                    ),
                  ],
                ),
                if (hasBackground) ...[
                  const SizedBox(height: 12),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children:
                          [
                                ...colors.map((c) => c.withOpacity(0.7)),
                                Colors.black54,
                              ]
                              .map(
                                (c) => GestureDetector(
                                  onTap: () => onBgColorChanged(c),
                                  child: AnimatedContainer(
                                    duration: const Duration(milliseconds: 200),
                                    width: 28,
                                    height: 28,
                                    margin: const EdgeInsets.only(right: 8),
                                    decoration: BoxDecoration(
                                      color: c,
                                      shape: BoxShape.circle,
                                      border: Border.all(
                                        color: bgColor == c
                                            ? _accent
                                            : Colors.white24,
                                        width: 2,
                                      ),
                                    ),
                                  ),
                                ),
                              )
                              .toList(),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _ToggleChip extends StatelessWidget {
  final String label;
  final bool isActive;
  final VoidCallback onTap;
  const _ToggleChip({
    required this.label,
    required this.isActive,
    required this.onTap,
  });
  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
      decoration: BoxDecoration(
        color: isActive ? _accent : Colors.white10,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(label, style: const TextStyle(color: Colors.white)),
    ),
  );
}

class _TextOverlay {
  String text;
  Offset position;
  Color color;
  bool hasOutline;
  bool hasBackground;
  Color backgroundColor;
  double scale;
  double rotation;
  _TextOverlay({
    required this.text,
    required this.position,
    this.color = Colors.white,
    this.hasOutline = false,
    this.hasBackground = false,
    this.backgroundColor = Colors.black54,
    this.scale = 1.0,
    this.rotation = 0.0,
  });
}

class _EmojiOverlay {
  String emoji;
  IconData? icon;
  Offset position;
  double scale;
  double rotation;
  _EmojiOverlay({
    required this.emoji,
    required this.position,
    this.icon,
    this.scale = 1.0,
    this.rotation = 0.0,
  });
}

class _MusicTrack {
  final String title;
  final String artist;
  final bool isTrending;
  _MusicTrack({
    required this.title,
    required this.artist,
    this.isTrending = false,
  });
}

class _TransformableOverlay extends StatefulWidget {
  final Offset initialPosition;
  final double initialScale;
  final double initialRotation;
  final Function(Offset, double, double) onUpdate;
  final VoidCallback onDelete;
  final Widget child;
  const _TransformableOverlay({
    super.key,
    required this.initialPosition,
    required this.initialScale,
    required this.initialRotation,
    required this.onUpdate,
    required this.onDelete,
    required this.child,
  });
  @override
  State<_TransformableOverlay> createState() => _TransformableOverlayState();
}

class _TransformableOverlayState extends State<_TransformableOverlay> {
  late Offset _pos;
  late double _scale;
  late double _rot;
  Offset? _startFocal;
  double _baseScale = 1.0;
  double _baseRot = 0.0;
  @override
  void initState() {
    super.initState();
    _pos = widget.initialPosition;
    _scale = widget.initialScale;
    _rot = widget.initialRotation;
  }

  @override
  Widget build(BuildContext context) => Positioned(
    left: _pos.dx,
    top: _pos.dy,
    child: GestureDetector(
      onScaleStart: (d) {
        _startFocal = d.focalPoint;
        _baseScale = _scale;
        _baseRot = _rot;
      },
      onScaleUpdate: (d) {
        setState(() {
          if (_startFocal != null) {
            _pos += d.focalPoint - _startFocal!;
            _startFocal = d.focalPoint;
          }
          _scale = (_baseScale * d.scale).clamp(0.5, 3.0);
          _rot = _baseRot + d.rotation;
        });
        widget.onUpdate(_pos, _scale, _rot);
      },
      onLongPress: widget.onDelete,
      child: Transform.rotate(
        angle: _rot,
        child: Transform.scale(
          scale: _scale,
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              widget.child,
              Positioned(
                top: -12,
                right: -12,
                child: GestureDetector(
                  onTap: widget.onDelete,
                  child: Container(
                    width: 26,
                    height: 26,
                    decoration: const BoxDecoration(
                      color: _accent,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.close,
                      color: Colors.white,
                      size: 16,
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

class _VideoPreview extends StatefulWidget {
  final XFile file;
  const _VideoPreview({required this.file});
  @override
  State<_VideoPreview> createState() => _VideoPreviewState();
}

class _VideoPreviewState extends State<_VideoPreview> {
  late VideoPlayerController _c;
  bool _init = false;
  @override
  void initState() {
    super.initState();
    if (kIsWeb) {
      _c = VideoPlayerController.networkUrl(Uri.parse(widget.file.path));
    } else {
      _c = VideoPlayerController.file(File(widget.file.path));
    }
    _c.initialize().then((_) {
      setState(() => _init = true);
      _c.setLooping(true);
      _c.play();
    });
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => _init
      ? Center(
          child: AspectRatio(
            aspectRatio: _c.value.aspectRatio,
            child: VideoPlayer(_c),
          ),
        )
      : const Center(child: CircularProgressIndicator(color: _accent));
}

class PublishContentPage extends StatefulWidget {
  final XFile? file;
  final bool isVideo;
  final bool isTextOnly;
  final _MusicTrack? selectedMusic;
  const PublishContentPage({
    super.key,
    this.file,
    this.isVideo = true,
    this.isTextOnly = false,
    this.selectedMusic,
  });
  @override
  State<PublishContentPage> createState() => _PublishContentPageState();
}

class _PublishContentPageState extends State<PublishContentPage> {
  final _captionCtrl = TextEditingController();
  final _hashtagCtrl = TextEditingController();
  final List<String> _hashtags = [];
  bool _isPosting = false;
  void _addHashtag() {
    final t = _hashtagCtrl.text.trim().replaceAll('#', '');
    if (t.isNotEmpty && !_hashtags.contains(t)) {
      setState(() {
        _hashtags.add(t);
        _hashtagCtrl.clear();
      });
    }
  }

  Future<void> _post() async {
    if (widget.file == null) return;

    setState(() => _isPosting = true);

    try {
      final sessionId = SessionManager().getSessionId;
      final uriString =
          '${AppConstants.baseUrl}/backend/uploadVideo.php' +
          (sessionId != null ? '?session_id=$sessionId' : '');
      final uri = Uri.parse(uriString);
      final request = http.MultipartRequest('POST', uri);

      // Add Headers (Cookie)
      final headers = SessionManager().headers;
      if (headers.containsKey('Cookie')) {
        request.headers['cookie'] = headers['Cookie']!;
      }

      // Add Fields
      request.fields['title'] = _captionCtrl.text.trim().isNotEmpty
          ? _captionCtrl.text.trim()
          : 'Untitled Video'; // Title is required backend side
      request.fields['description'] = _captionCtrl.text.trim();
      request.fields['hashtags'] = jsonEncode(_hashtags);
      request.fields['is_clip'] = (!widget.isVideo)
          .toString(); // Assuming image is not clip? Or is it?
      // Actually widget.isVideo distinguishes video vs image.
      // User request implies video posting.
      // If widget.isVideo is true, it's a clip/video.
      request.fields['is_clip'] = 'true';

      // Add File
      if (kIsWeb) {
        // On web we need to read bytes
        final bytes = await widget.file!.readAsBytes();
        request.files.add(
          http.MultipartFile.fromBytes(
            'video',
            bytes,
            filename: widget.file!.name,
          ),
        );
      } else {
        request.files.add(
          await http.MultipartFile.fromPath('video', widget.file!.path),
        );
      }

      // Send
      final streamedResponse = await request.send();
      final resBody = await streamedResponse.stream.bytesToString();

      debugPrint('UPLOAD RESPONSE: ${streamedResponse.statusCode}');
      debugPrint('UPLOAD BODY: $resBody');

      final response = http.Response(
        resBody,
        streamedResponse.statusCode,
        headers: streamedResponse.headers,
        request: streamedResponse.request,
        isRedirect: streamedResponse.isRedirect,
        persistentConnection: streamedResponse.persistentConnection,
        reasonPhrase: streamedResponse.reasonPhrase,
      );

      if (mounted) {
        setState(() => _isPosting = false);

        if (response.statusCode == 200) {
          final data = jsonDecode(response.body);
          if (data['success'] == true) {
            Navigator.popUntil(context, (r) => r.isFirst);
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Posted! 🎉'),
                backgroundColor: _accent,
              ),
            );
          } else {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('Error: ${data['message']}'),
                backgroundColor: Colors.red,
              ),
            );
          }
        } else if (response.statusCode == 401) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Session expired. Please log in again.'),
              backgroundColor: Colors.red,
            ),
          );
          // Optional: Redirect to login or clear session
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Server Error: ${response.statusCode}'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isPosting = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Upload failed: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    extendBodyBehindAppBar: true,
    backgroundColor: const Color(0xFF0A0A0F),
    appBar: AppBar(
      backgroundColor: Colors.transparent,
      elevation: 0,
      leading: Padding(
        padding: const EdgeInsets.all(8.0),
        child: _AnimatedIconButton(
          icon: Icons.arrow_back_ios_rounded,
          onTap: () => Navigator.pop(context),
        ),
      ),
      title: const Text(
        'New Post',
        style: TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.bold,
          fontSize: 20,
          letterSpacing: 0.5,
        ),
      ),
      centerTitle: true,
    ),
    body: Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            Colors.black,
            const Color(0xFF0F0F1A),
            const Color(0xFF151525),
          ],
        ),
      ),
      child: SingleChildScrollView(
        padding: EdgeInsets.fromLTRB(
          20,
          MediaQuery.of(context).padding.top + 60,
          20,
          40,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Preview Section
            if (widget.file != null)
              Center(
                child: Container(
                  width: 140,
                  height: 200,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(24),
                    boxShadow: [
                      BoxShadow(
                        color: _accent.withOpacity(0.2),
                        blurRadius: 30,
                        spreadRadius: -10,
                      ),
                    ],
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(24),
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        widget.isVideo
                            ? Container(
                                color: Colors.grey[900],
                                child: const Center(
                                  child: Icon(
                                    Icons.play_circle_fill,
                                    color: Colors.white,
                                    size: 48,
                                  ),
                                ),
                              )
                            : (kIsWeb
                                  ? Image.network(
                                      widget.file!.path,
                                      fit: BoxFit.cover,
                                    )
                                  : Image.file(
                                      File(widget.file!.path),
                                      fit: BoxFit.cover,
                                    )),
                        Container(
                          decoration: BoxDecoration(
                            border: Border.all(
                              color: Colors.white.withOpacity(0.15),
                              width: 1,
                            ),
                            borderRadius: BorderRadius.circular(24),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),

            if (widget.selectedMusic != null) ...[
              const SizedBox(height: 16),
              Center(
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: _accent.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: _accent.withOpacity(0.3)),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.music_note, color: _accent, size: 16),
                      const SizedBox(width: 8),
                      Text(
                        widget.selectedMusic!.title,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],

            const SizedBox(height: 32),

            // Input Section
            _buildSectionHeader('Caption', Icons.short_text_rounded),
            const SizedBox(height: 12),
            Container(
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.05),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: Colors.white.withOpacity(0.1)),
              ),
              child: TextField(
                controller: _captionCtrl,
                style: const TextStyle(color: Colors.white, fontSize: 15),
                maxLines: 4,
                decoration: InputDecoration(
                  hintText: "What's on your mind? ...",
                  hintStyle: TextStyle(color: Colors.white.withOpacity(0.3)),
                  contentPadding: const EdgeInsets.all(20),
                  border: InputBorder.none,
                ),
              ),
            ),

            const SizedBox(height: 24),

            _buildSectionHeader('Hashtags', Icons.tag_rounded),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: Container(
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.05),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: Colors.white.withOpacity(0.1)),
                    ),
                    child: TextField(
                      controller: _hashtagCtrl,
                      style: const TextStyle(color: Colors.white),
                      decoration: InputDecoration(
                        hintText: 'Add hashtag',
                        hintStyle: TextStyle(
                          color: Colors.white.withOpacity(0.3),
                        ),
                        prefixIcon: const Icon(
                          Icons.tag,
                          color: _accent,
                          size: 18,
                        ),
                        border: InputBorder.none,
                        contentPadding: const EdgeInsets.symmetric(
                          vertical: 14,
                        ),
                      ),
                      onSubmitted: (_) => _addHashtag(),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                GestureDetector(
                  onTap: _addHashtag,
                  child: Container(
                    width: 52,
                    height: 48,
                    decoration: BoxDecoration(
                      color: _accent,
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: [
                        BoxShadow(
                          color: _accent.withOpacity(0.3),
                          blurRadius: 12,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: const Icon(Icons.add, color: Colors.white),
                  ),
                ),
              ],
            ),

            if (_hashtags.isNotEmpty) ...[
              const SizedBox(height: 16),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: _hashtags
                    .asMap()
                    .entries
                    .map((e) => _buildHashtagChip(e.value, e.key))
                    .toList(),
              ),
            ],

            const SizedBox(height: 48),

            // Post Button
            SizedBox(
              width: double.infinity,
              height: 56,
              child: _AnimatedButton(
                label: _isPosting ? 'Posting...' : 'Post 🚀',
                onTap: _isPosting ? () {} : _post,
              ),
            ),
          ],
        ),
      ),
    ),
  );

  Widget _buildSectionHeader(String title, IconData icon) {
    return Row(
      children: [
        Icon(icon, color: Colors.white54, size: 20),
        const SizedBox(width: 8),
        Text(
          title,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 14,
            fontWeight: FontWeight.bold,
            letterSpacing: 0.5,
          ),
        ),
      ],
    );
  }

  Widget _buildHashtagChip(String label, int index) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: Duration(milliseconds: 300 + index * 50),
      curve: Curves.easeOutBack,
      builder: (_, v, c) => Transform.scale(scale: v, child: c),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: _accent.withOpacity(0.12),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: _accent.withOpacity(0.2)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              '#$label',
              style: const TextStyle(
                color: _accent,
                fontWeight: FontWeight.w600,
                fontSize: 13,
              ),
            ),
            const SizedBox(width: 8),
            GestureDetector(
              onTap: () => setState(() => _hashtags.remove(label)),
              child: Icon(
                Icons.close_rounded,
                size: 14,
                color: Colors.white.withOpacity(0.4),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MusicLibraryPage extends StatefulWidget {
  final Function(_MusicTrack) onSelect;
  const _MusicLibraryPage({required this.onSelect});
  @override
  State<_MusicLibraryPage> createState() => _MusicLibraryPageState();
}

class _MusicLibraryPageState extends State<_MusicLibraryPage> {
  final _searchCtrl = TextEditingController();
  String _q = '';
  final _tracks = [
    _MusicTrack(
      title: 'Blinding Lights',
      artist: 'The Weeknd',
      isTrending: true,
    ),
    _MusicTrack(title: 'Levitating', artist: 'Dua Lipa', isTrending: true),
    _MusicTrack(title: 'Stay', artist: 'Kid LAROI', isTrending: true),
    _MusicTrack(title: 'Good 4 U', artist: 'Olivia Rodrigo', isTrending: true),
    _MusicTrack(title: 'Montero', artist: 'Lil Nas X', isTrending: true),
    _MusicTrack(title: 'Peaches', artist: 'Justin Bieber'),
    _MusicTrack(title: 'Watermelon Sugar', artist: 'Harry Styles'),
    _MusicTrack(title: 'Dynamite', artist: 'BTS'),
    _MusicTrack(title: 'positions', artist: 'Ariana Grande'),
    _MusicTrack(title: 'Butter', artist: 'BTS'),
  ];
  List<_MusicTrack> get _filtered => _q.isEmpty
      ? _tracks
      : _tracks
            .where(
              (t) =>
                  t.title.toLowerCase().contains(_q.toLowerCase()) ||
                  t.artist.toLowerCase().contains(_q.toLowerCase()),
            )
            .toList();
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFF0A0A0F),
    appBar: AppBar(
      backgroundColor: Colors.transparent,
      elevation: 0,
      leading: IconButton(
        icon: const Icon(Icons.close, color: Colors.white),
        onPressed: () => Navigator.pop(context),
      ),
      title: const Text(
        'Music',
        style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
      ),
      centerTitle: true,
    ),
    body: Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: TextField(
            controller: _searchCtrl,
            style: const TextStyle(color: Colors.white),
            onChanged: (v) => setState(() => _q = v),
            decoration: InputDecoration(
              hintText: 'Search songs...',
              hintStyle: const TextStyle(color: Colors.white24),
              prefixIcon: const Icon(Icons.search, color: Colors.white38),
              filled: true,
              fillColor: Colors.white.withOpacity(0.06),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(16),
                borderSide: BorderSide.none,
              ),
            ),
          ),
        ),
        if (_q.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: [
                Icon(
                  Icons.local_fire_department,
                  color: Colors.orange,
                  size: 20,
                ),
                SizedBox(width: 8),
                Text(
                  'Trending',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
          ),
        if (_q.isEmpty)
          SizedBox(
            height: 90,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.all(16),
              children: _tracks
                  .where((t) => t.isTrending)
                  .toList()
                  .asMap()
                  .entries
                  .map(
                    (e) => TweenAnimationBuilder<double>(
                      tween: Tween(begin: 0, end: 1),
                      duration: Duration(milliseconds: 300 + e.key * 100),
                      curve: Curves.easeOutBack,
                      builder: (_, v, c) => Transform.scale(scale: v, child: c),
                      child: GestureDetector(
                        onTap: () {
                          widget.onSelect(e.value);
                          Navigator.pop(context);
                        },
                        child: Container(
                          width: 130,
                          margin: const EdgeInsets.only(right: 12),
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              colors: [
                                _accent.withOpacity(0.3),
                                _accent.withOpacity(0.1),
                              ],
                            ),
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(
                                e.value.title,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w600,
                                  fontSize: 12,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                              Text(
                                e.value.artist,
                                style: const TextStyle(
                                  color: Colors.white54,
                                  fontSize: 10,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  )
                  .toList(),
            ),
          ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: _filtered
                .asMap()
                .entries
                .map(
                  (e) => TweenAnimationBuilder<double>(
                    tween: Tween(begin: 0, end: 1),
                    duration: Duration(milliseconds: 200 + e.key * 50),
                    builder: (_, v, c) => Opacity(
                      opacity: v,
                      child: Transform.translate(
                        offset: Offset(0, 20 * (1 - v)),
                        child: c,
                      ),
                    ),
                    child: GestureDetector(
                      onTap: () {
                        widget.onSelect(e.value);
                        Navigator.pop(context);
                      },
                      child: Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        padding: const EdgeInsets.all(16),
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
                                color: _accent,
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: const Icon(
                                Icons.music_note,
                                color: Colors.white,
                              ),
                            ),
                            const SizedBox(width: 14),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    e.value.title,
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                  Text(
                                    e.value.artist,
                                    style: const TextStyle(
                                      color: Colors.white38,
                                      fontSize: 13,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const Icon(
                              Icons.add_circle_outline,
                              color: Colors.white24,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                )
                .toList(),
          ),
        ),
      ],
    ),
  );
}

class _ScreenFlashGlow extends StatelessWidget {
  const _ScreenFlashGlow(); // No alignment needed

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        // Top Edge
        Align(
          alignment: Alignment.topCenter,
          child: Container(
            height: 100,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Colors.white, Colors.white.withOpacity(0.0)],
              ),
            ),
          ),
        ),
        // Bottom Edge
        Align(
          alignment: Alignment.bottomCenter,
          child: Container(
            height: 100,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.bottomCenter,
                end: Alignment.topCenter,
                colors: [Colors.white, Colors.white.withOpacity(0.0)],
              ),
            ),
          ),
        ),
        // Left Edge
        Align(
          alignment: Alignment.centerLeft,
          child: Container(
            width: 60,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.centerLeft,
                end: Alignment.centerRight,
                colors: [Colors.white, Colors.white.withOpacity(0.0)],
              ),
            ),
          ),
        ),
        // Right Edge
        Align(
          alignment: Alignment.centerRight,
          child: Container(
            width: 60,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.centerRight,
                end: Alignment.centerLeft,
                colors: [Colors.white, Colors.white.withOpacity(0.0)],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

// --- Premium Helper Widgets ---

class _PremiumIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  final bool isActive;

  const _PremiumIconButton({
    required this.icon,
    required this.onTap,
    this.isActive = false,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: isActive ? Colors.white : Colors.transparent,
          shape: BoxShape.circle,
          border: Border.all(color: Colors.white.withOpacity(0.15), width: 1),
        ),
        child: Icon(
          icon,
          color: isActive ? Colors.black : Colors.white,
          size: 20,
        ),
      ),
    );
  }
}

class _PremiumSideButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool isVisible;
  final VoidCallback onTap;
  final Color? color;

  const _PremiumSideButton({
    required this.icon,
    required this.label,
    required this.isVisible,
    required this.onTap,
    this.color,
  });

  @override
  Widget build(BuildContext context) {
    return AnimatedOpacity(
      opacity: isVisible ? 1.0 : 0.0,
      duration: const Duration(milliseconds: 200),
      child: IgnorePointer(
        ignoring: !isVisible,
        child: GestureDetector(
          onTap: onTap,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color:
                      color?.withOpacity(0.2) ?? Colors.white.withOpacity(0.1),
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: color ?? Colors.white.withOpacity(0.2),
                    width: 1,
                  ),
                ),
                child: Icon(icon, color: color ?? Colors.white, size: 22),
              ),
              const SizedBox(height: 6),
              Text(
                label,
                style: TextStyle(
                  color: Colors.white.withOpacity(0.7),
                  fontSize: 11,
                  fontWeight: FontWeight.w500,
                  letterSpacing: 0.3,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
