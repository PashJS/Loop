import 'dart:ui' as ui;
import 'package:flutter/material.dart';

/// High-Fidelity Liquid Glass Bar
///
/// Implements:
/// 1. Real-time background refraction (using captured ui.Image)
/// 2. Depth simulation with inner shadows and specular highlights
/// 3. Top lightning border effect (NO rainbow/iridescent border)
class LiquidGlassBar extends StatefulWidget {
  final double height;
  final double borderRadius;
  final Widget child;
  final ValueNotifier<ui.Image?>? backgroundNotifier;
  final GlobalKey? backgroundKey;
  final bool isMessaging;

  const LiquidGlassBar({
    super.key,
    required this.height,
    required this.borderRadius,
    required this.child,
    this.backgroundNotifier,
    this.backgroundKey,
    this.isMessaging = false,
  });

  @override
  State<LiquidGlassBar> createState() => _LiquidGlassBarState();
}

class _LiquidGlassBarState extends State<LiquidGlassBar>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 10000),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(widget.borderRadius),
      child: Stack(
        children: [
          // 1. Refraction Layer (Background distortion)
          if (widget.backgroundNotifier != null && widget.backgroundKey != null)
            Positioned.fill(
              child: AnimatedBuilder(
                animation: widget.backgroundNotifier!,
                builder: (context, _) {
                  if (widget.backgroundNotifier!.value == null) {
                    return const SizedBox.shrink();
                  }
                  return CustomPaint(
                    painter: _RefractionPainter(
                      image: widget.backgroundNotifier!.value!,
                      context: context,
                      backgroundKey: widget.backgroundKey!,
                      borderRadius: widget.borderRadius,
                      time: _controller.value,
                    ),
                  );
                },
              ),
            ),

          // 2. Blur / Frost (Standard Diffusion)
          Positioned.fill(
            child: BackdropFilter(
              filter: ui.ImageFilter.blur(sigmaX: 15, sigmaY: 15),
              child: Container(color: Colors.black.withOpacity(0.4)),
            ),
          ),

          // 3. Surface effects (Top lightning only, no rainbow)
          Positioned.fill(
            child: AnimatedBuilder(
              animation: _controller,
              builder: (context, _) {
                return CustomPaint(
                  painter: _GlassSurfacePainter(
                    borderRadius: widget.borderRadius,
                    time: _controller.value,
                  ),
                );
              },
            ),
          ),

          // 4. Content
          SizedBox(
            height: widget.height,
            child: Center(child: widget.child),
          ),
        ],
      ),
    );
  }
}

/// Handles the "Refraction" by mapping the background image
/// to this widget's coordinate space with a slight distortion.
class _RefractionPainter extends CustomPainter {
  final ui.Image image;
  final BuildContext context;
  final GlobalKey backgroundKey;
  final double borderRadius;
  final double time;

  _RefractionPainter({
    required this.image,
    required this.context,
    required this.backgroundKey,
    required this.borderRadius,
    required this.time,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final RenderBox? box = context.findRenderObject() as RenderBox?;
    final RenderBox? bgBox =
        backgroundKey.currentContext?.findRenderObject() as RenderBox?;

    if (box == null || bgBox == null) return;

    final Offset localPos = box.localToGlobal(Offset.zero, ancestor: bgBox);
    final Rect srcRect = localPos & size;

    if (srcRect.left < 0 ||
        srcRect.top < 0 ||
        srcRect.right > image.width ||
        srcRect.bottom > image.height) {
      return;
    }

    final paint = Paint();

    // Fake optical distortion (magnification)
    final double zoom = 1.05;
    final double zoomedW = srcRect.width * zoom;
    final double zoomedH = srcRect.height * zoom;
    final double dX = (zoomedW - srcRect.width) / 2;
    final double dY = (zoomedH - srcRect.height) / 2;

    final Rect distortedSrc = Rect.fromLTWH(
      (srcRect.left - dX).clamp(0.0, image.width.toDouble()),
      (srcRect.top - dY).clamp(0.0, image.height.toDouble()),
      srcRect.width + dX * 2,
      srcRect.height + dY * 2,
    );

    canvas.drawImageRect(
      image,
      distortedSrc,
      Rect.fromLTWH(0, 0, size.width, size.height),
      paint,
    );
  }

  @override
  bool shouldRepaint(covariant _RefractionPainter old) => true;
}

/// Draws the Specular highlights, Inner shadows, and Top Lightning
/// to give volume - NO rainbow/iridescent border.
class _GlassSurfacePainter extends CustomPainter {
  final double borderRadius;
  final double time;

  _GlassSurfacePainter({required this.borderRadius, required this.time});

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Rect.fromLTWH(0, 0, size.width, size.height);
    final rrect = RRect.fromRectAndRadius(rect, Radius.circular(borderRadius));

    // 1. Subtle inner glow (very subtle highlight moving across)
    final highlightPaint = Paint()
      ..style = PaintingStyle.fill
      ..shader = ui.Gradient.linear(
        const Offset(0, 0),
        Offset(size.width, size.height),
        [
          Colors.white.withOpacity(0.0),
          Colors.white.withOpacity(0.03),
          Colors.white.withOpacity(0.0),
        ],
        [
          (time % 1.0 - 0.2).clamp(0.0, 1.0),
          (time % 1.0).clamp(0.0, 1.0),
          (time % 1.0 + 0.2).clamp(0.0, 1.0),
        ],
      );

    canvas.drawRRect(rrect, highlightPaint);

    // 2. Inner Depth Shadow (darkens edges for thickness)
    final shadowPaint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 1.5)
      ..color = Colors.black.withOpacity(0.15);

    canvas.drawRRect(rrect.deflate(0.5), shadowPaint);

    // 3. Top Lightning Border (the signature glass edge glow)
    // Clips to only draw the top portion of the rounded rect
    canvas.save();
    canvas.clipRect(Rect.fromLTWH(0, 0, size.width, size.height / 2));

    final lightningPaint = Paint()
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

    canvas.drawRRect(rrect, lightningPaint);
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _GlassSurfacePainter old) => old.time != time;
}
