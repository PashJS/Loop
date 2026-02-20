import 'dart:async';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';
import 'package:chewie/chewie.dart';

class CustomVideoControls extends StatefulWidget {
  final String title;
  final VoidCallback? onNext;

  const CustomVideoControls({super.key, required this.title, this.onNext});

  @override
  State<CustomVideoControls> createState() => _CustomVideoControlsState();
}

class _CustomVideoControlsState extends State<CustomVideoControls>
    with TickerProviderStateMixin {
  VideoPlayerController? _controller;
  ChewieController? _chewieController;

  bool _hideStuff = false;
  Timer? _hideTimer;
  bool _dragging = false;
  double? _dragValue;

  // Seek Animations
  bool _showForwardAnim = false;
  bool _showRewindAnim = false;
  late AnimationController _forwardAnimController;
  late AnimationController _rewindAnimController;

  // Fade animation for controls
  late AnimationController _fadeController;
  late Animation<double> _fadeAnimation;

  @override
  void initState() {
    super.initState();
    _forwardAnimController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 400),
    );
    _rewindAnimController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 400),
    );

    // Fade controller for smooth show/hide
    _fadeController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 250),
    );
    _fadeAnimation = CurvedAnimation(
      parent: _fadeController,
      curve: Curves.easeInOut,
    );
    _fadeController.forward(); // Start visible

    _forwardAnimController.addStatusListener((s) {
      if (s == AnimationStatus.completed) {
        setState(() => _showForwardAnim = false);
      }
    });
    _rewindAnimController.addStatusListener((s) {
      if (s == AnimationStatus.completed) {
        setState(() => _showRewindAnim = false);
      }
    });
  }

  @override
  void didChangeDependencies() {
    _chewieController = ChewieController.of(context);
    _controller = _chewieController?.videoPlayerController;
    super.didChangeDependencies();
  }

  @override
  void dispose() {
    _hideTimer?.cancel();
    _forwardAnimController.dispose();
    _rewindAnimController.dispose();
    _fadeController.dispose();
    super.dispose();
  }

  void _showControls() {
    _hideTimer?.cancel();
    _fadeController.forward();
    setState(() => _hideStuff = false);
    _startHideTimer();
  }

  void _hideControls() {
    _fadeController.reverse().then((_) {
      if (mounted) setState(() => _hideStuff = true);
    });
  }

  void _toggleControls() {
    if (_hideStuff) {
      _showControls();
    } else {
      _hideControls();
    }
  }

  void _playPause() {
    if (_controller == null) return;
    if (_controller!.value.isPlaying) {
      _controller!.pause();
      _showControls();
    } else {
      _controller!.play();
      _startHideTimer();
    }
  }

  void _seekRelative(Duration diff) {
    if (_controller == null || !_controller!.value.isInitialized) return;
    final duration = _controller!.value.duration;
    if (duration <= Duration.zero) return;

    var newPos = _controller!.value.position + diff;
    if (newPos < Duration.zero) newPos = Duration.zero;
    if (newPos > duration) newPos = duration;

    _controller!.seekTo(newPos);

    if (diff.isNegative) {
      setState(() => _showRewindAnim = true);
      _rewindAnimController.forward(from: 0.0);
    } else {
      setState(() => _showForwardAnim = true);
      _forwardAnimController.forward(from: 0.0);
    }
    _startHideTimer();
  }

  void _startHideTimer() {
    _hideTimer?.cancel();
    _hideTimer = Timer(const Duration(seconds: 3), () {
      if (mounted && (_controller?.value.isPlaying ?? false) && !_dragging) {
        _hideControls();
      }
    });
  }

  void _toggleFullScreen() {
    _chewieController?.toggleFullScreen();
  }

  String _formatDuration(Duration d) {
    String twoDigits(int n) => n.toString().padLeft(2, '0');
    if (d.inHours > 0) {
      return '${d.inHours}:${twoDigits(d.inMinutes.remainder(60))}:${twoDigits(d.inSeconds.remainder(60))}';
    }
    return '${d.inMinutes.remainder(60)}:${twoDigits(d.inSeconds.remainder(60))}';
  }

  @override
  Widget build(BuildContext context) {
    if (_controller == null) return const SizedBox.shrink();

    // Check orientation for auto-fullscreen
    final isLandscape =
        MediaQuery.of(context).orientation == Orientation.landscape;

    return LayoutBuilder(
      builder: (context, constraints) {
        return GestureDetector(
          onTap: _toggleControls,
          behavior: HitTestBehavior.opaque,
          child: Stack(
            fit: StackFit.expand,
            children: [
              // Ghost Seek Animations (always visible when active)
              if (_showRewindAnim)
                Positioned(
                  left: constraints.maxWidth * 0.15,
                  top: 0,
                  bottom: 0,
                  child: Center(
                    child: _GhostArrow(
                      controller: _rewindAnimController,
                      isForward: false,
                    ),
                  ),
                ),
              if (_showForwardAnim)
                Positioned(
                  right: constraints.maxWidth * 0.15,
                  top: 0,
                  bottom: 0,
                  child: Center(
                    child: _GhostArrow(
                      controller: _forwardAnimController,
                      isForward: true,
                    ),
                  ),
                ),

              // Fading Controls Layer
              FadeTransition(
                opacity: _fadeAnimation,
                child: _hideStuff
                    ? const SizedBox.shrink()
                    : Stack(
                        fit: StackFit.expand,
                        children: [
                          // Background Dim
                          Container(color: Colors.black.withOpacity(0.4)),

                          // Title (Top)
                          Positioned(
                            top: 12,
                            left: 16,
                            right: 16,
                            child: SafeArea(
                              bottom: false,
                              child: Text(
                                widget.title,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 15,
                                  fontWeight: FontWeight.w600,
                                  shadows: [
                                    Shadow(blurRadius: 8, color: Colors.black),
                                  ],
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ),

                          // Center Controls
                          Center(
                            child: ValueListenableBuilder(
                              valueListenable: _controller!,
                              builder: (context, VideoPlayerValue value, child) {
                                return Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    _LiquidGlassButton(
                                      icon: Icons.replay_10_rounded,
                                      onTap: () => _seekRelative(
                                        const Duration(seconds: -10),
                                      ),
                                      size: 48,
                                    ),
                                    const SizedBox(width: 32),
                                    value.isBuffering
                                        ? Container(
                                            width: 64,
                                            height: 64,
                                            decoration: BoxDecoration(
                                              shape: BoxShape.circle,
                                              color: Colors.black.withOpacity(
                                                0.3,
                                              ),
                                            ),
                                            child: const Center(
                                              child: SizedBox(
                                                width: 32,
                                                height: 32,
                                                child:
                                                    CircularProgressIndicator(
                                                      color: Colors.white,
                                                      strokeWidth: 2.5,
                                                    ),
                                              ),
                                            ),
                                          )
                                        : _LiquidGlassButton(
                                            icon: value.isPlaying
                                                ? Icons.pause_rounded
                                                : Icons.play_arrow_rounded,
                                            onTap: _playPause,
                                            size: 64,
                                            iconSize: 36,
                                          ),
                                    const SizedBox(width: 32),
                                    _LiquidGlassButton(
                                      icon: Icons.skip_next_rounded,
                                      onTap: widget.onNext ?? () {},
                                      size: 48,
                                    ),
                                  ],
                                );
                              },
                            ),
                          ),

                          // Double-Tap Zones
                          Positioned.fill(
                            child: Row(
                              children: [
                                Expanded(
                                  child: GestureDetector(
                                    onDoubleTap: () => _seekRelative(
                                      const Duration(seconds: -10),
                                    ),
                                    behavior: HitTestBehavior.translucent,
                                  ),
                                ),
                                Expanded(
                                  child: GestureDetector(
                                    onDoubleTap: _playPause,
                                    behavior: HitTestBehavior.translucent,
                                  ),
                                ),
                                Expanded(
                                  child: GestureDetector(
                                    onDoubleTap: () => _seekRelative(
                                      const Duration(seconds: 10),
                                    ),
                                    behavior: HitTestBehavior.translucent,
                                  ),
                                ),
                              ],
                            ),
                          ),

                          // Bottom Progress Bar
                          Positioned(
                            bottom: 8,
                            left: 12,
                            right: 12,
                            child: SafeArea(
                              top: false,
                              child: ValueListenableBuilder(
                                valueListenable: _controller!,
                                builder: (context, VideoPlayerValue value, child) {
                                  final duration = value.duration.inMilliseconds
                                      .toDouble();
                                  final position = value.position.inMilliseconds
                                      .toDouble();
                                  final max = duration > 0 ? duration : 1.0;
                                  final current = _dragging
                                      ? _dragValue!
                                      : position.clamp(0.0, max);

                                  return Column(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      // Scrub Tooltip
                                      if (_dragging && _dragValue != null)
                                        Container(
                                          padding: const EdgeInsets.symmetric(
                                            horizontal: 10,
                                            vertical: 4,
                                          ),
                                          margin: const EdgeInsets.only(
                                            bottom: 8,
                                          ),
                                          decoration: BoxDecoration(
                                            color: Colors.white,
                                            borderRadius: BorderRadius.circular(
                                              6,
                                            ),
                                            boxShadow: [
                                              BoxShadow(
                                                color: Colors.black.withOpacity(
                                                  0.3,
                                                ),
                                                blurRadius: 8,
                                              ),
                                            ],
                                          ),
                                          child: Text(
                                            _formatDuration(
                                              Duration(
                                                milliseconds: _dragValue!
                                                    .toInt(),
                                              ),
                                            ),
                                            style: const TextStyle(
                                              color: Colors.black,
                                              fontWeight: FontWeight.bold,
                                              fontSize: 13,
                                            ),
                                          ),
                                        ),

                                      // Time + Slider + Fullscreen Row
                                      Row(
                                        children: [
                                          Text(
                                            _formatDuration(value.position),
                                            style: const TextStyle(
                                              color: Colors.white,
                                              fontSize: 11,
                                              fontWeight: FontWeight.w500,
                                            ),
                                          ),
                                          const SizedBox(width: 8),
                                          Expanded(
                                            child: SliderTheme(
                                              data: SliderTheme.of(context).copyWith(
                                                activeTrackColor: const Color(
                                                  0xFF3EA6FF,
                                                ),
                                                inactiveTrackColor: Colors.white
                                                    .withOpacity(0.25),
                                                thumbColor: const Color(
                                                  0xFF3EA6FF,
                                                ),
                                                trackHeight: 3.0,
                                                thumbShape:
                                                    const RoundSliderThumbShape(
                                                      enabledThumbRadius: 7,
                                                    ),
                                                overlayShape:
                                                    const RoundSliderOverlayShape(
                                                      overlayRadius: 14,
                                                    ),
                                              ),
                                              child: Slider(
                                                min: 0.0,
                                                max: max,
                                                value: current,
                                                onChanged: (v) {
                                                  setState(() {
                                                    _dragging = true;
                                                    _dragValue = v;
                                                  });
                                                  _hideTimer?.cancel();
                                                },
                                                onChangeEnd: (v) {
                                                  _controller!.seekTo(
                                                    Duration(
                                                      milliseconds: v.toInt(),
                                                    ),
                                                  );
                                                  setState(() {
                                                    _dragging = false;
                                                    _dragValue = null;
                                                  });
                                                  _startHideTimer();
                                                },
                                              ),
                                            ),
                                          ),
                                          const SizedBox(width: 8),
                                          Text(
                                            _formatDuration(value.duration),
                                            style: const TextStyle(
                                              color: Colors.white,
                                              fontSize: 11,
                                              fontWeight: FontWeight.w500,
                                            ),
                                          ),
                                          const SizedBox(width: 12),
                                          // Fullscreen Button
                                          GestureDetector(
                                            onTap: _toggleFullScreen,
                                            child: Icon(
                                              isLandscape
                                                  ? Icons
                                                        .fullscreen_exit_rounded
                                                  : Icons.fullscreen_rounded,
                                              color: Colors.white,
                                              size: 26,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ],
                                  );
                                },
                              ),
                            ),
                          ),
                        ],
                      ),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Liquid Glass Button with blur and border-only lightning
class _LiquidGlassButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  final double size;
  final double? iconSize;

  const _LiquidGlassButton({
    required this.icon,
    required this.onTap,
    required this.size,
    this.iconSize,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: ClipOval(
        child: BackdropFilter(
          filter: ui.ImageFilter.blur(sigmaX: 12, sigmaY: 12),
          child: Container(
            width: size,
            height: size,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: Colors.white.withOpacity(0.08),
            ),
            child: CustomPaint(
              painter: _LightningBorderPainter(),
              child: Center(
                child: Icon(
                  icon,
                  color: Colors.white,
                  size: iconSize ?? size * 0.5,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Draws lightning border only (no fill glow)
class _LightningBorderPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;
    final center = rect.center;
    final radius = size.width / 2;

    // Subtle base border
    final basePaint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.0
      ..color = Colors.white.withOpacity(0.15);
    canvas.drawCircle(center, radius - 0.5, basePaint);

    // Lightning gradient on top half of border
    final lightningPaint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5
      ..shader = ui.Gradient.linear(
        Offset(center.dx, 0),
        Offset(center.dx, size.height * 0.6),
        [Colors.white.withOpacity(0.7), Colors.white.withOpacity(0.0)],
      );
    canvas.drawCircle(center, radius - 0.75, lightningPaint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

/// Ghost arrow animation for seek feedback
class _GhostArrow extends StatelessWidget {
  final AnimationController controller;
  final bool isForward;

  const _GhostArrow({required this.controller, required this.isForward});

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, child) {
        final opacity = (1.0 - controller.value).clamp(0.0, 1.0);
        final xShift = (isForward ? 1.0 : -1.0) * (controller.value * 50);

        return Transform.translate(
          offset: Offset(xShift, 0),
          child: Opacity(
            opacity: opacity,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  isForward
                      ? Icons.fast_forward_rounded
                      : Icons.fast_rewind_rounded,
                  color: Colors.white,
                  size: 44,
                ),
                const SizedBox(height: 4),
                const Text(
                  "10s",
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                    shadows: [Shadow(blurRadius: 6, color: Colors.black)],
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
