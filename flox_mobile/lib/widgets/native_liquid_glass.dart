import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// A native liquid glass input bar.
/// On Android, this uses a PlatformView to embed a high-performance Jetpack Compose
/// component with AGSL shaders.
/// On iOS/Others, it falls back to a high-quality Blur effect.
class NativeLiquidGlassBar extends StatelessWidget {
  final double height;
  final double borderRadius;
  final Widget child;

  // NOTE: This implementation relies on the Native View (Android) to handle its own
  // internal liquid distortion. It does NOT refract the Flutter background behind it
  // unless we pass a captured image, which is expensive.
  // However, this aligns with the Native Jetpack Compose implementation requested.
  final ValueNotifier<ui.Image?>? backgroundNotifier;

  const NativeLiquidGlassBar({
    super.key,
    required this.height,
    required this.borderRadius,
    required this.child,
    this.backgroundNotifier,
  });

  @override
  Widget build(BuildContext context) {
    // Check if we are on Android
    // TEMPORARILY DISABLED: Reverting to Flutter implementation to stop the crash.
    // We will re-enable the native view once we isolate the cause.
    if (false && Theme.of(context).platform == TargetPlatform.android) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(borderRadius),
        child: SizedBox(
          height: height,
          child: Stack(
            children: [
              // The Native Android View (Jetpack Compose)
              // This uses the 'liquid_glass_input_bar' factory registered in MainActivity
              // The Native Android View (Jetpack Compose)
              // This uses the 'liquid_glass_input_bar' factory registered in MainActivity
              AndroidView(
                viewType: 'liquid_glass_input_bar',
                creationParams: {
                  'height': height,
                  'borderRadius': borderRadius,
                },
                creationParamsCodec: const StandardMessageCodec(),
              ),

              // Overlay child content (Flutter widgets) on top
              child,

              // Top lightning border (drawn on top of everything)
              IgnorePointer(
                child: CustomPaint(
                  painter: _TopLightningBorderPainter(
                    borderRadius: borderRadius,
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    }

    // Fallback for iOS/Other (using Blur)
    return ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius),
      child: SizedBox(
        height: height,
        child: Stack(
          children: [
            BackdropFilter(
              filter: ui.ImageFilter.blur(sigmaX: 20, sigmaY: 20),
              child: Container(color: Colors.white.withOpacity(0.1)),
            ),
            child,
            IgnorePointer(
              child: CustomPaint(
                painter: _TopLightningBorderPainter(borderRadius: borderRadius),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Draws a premium glowing border on the top edge
class _TopLightningBorderPainter extends CustomPainter {
  final double borderRadius;

  _TopLightningBorderPainter({required this.borderRadius});

  @override
  void paint(Canvas canvas, Size size) {
    if (size.width == 0 || size.height == 0) return;

    // Create a path that exactly matches the top border of the rounded rectangle
    final path = Path();
    path.moveTo(0, borderRadius);
    // Top-left corner
    path.arcToPoint(
      Offset(borderRadius, 0),
      radius: Radius.circular(borderRadius),
      clockwise: true,
    );
    // Top edge
    path.lineTo(size.width - borderRadius, 0);
    // Top-right corner
    path.arcToPoint(
      Offset(size.width, borderRadius),
      radius: Radius.circular(borderRadius),
      clockwise: true,
    );

    final paint = Paint()
      ..shader = ui.Gradient.linear(
        const Offset(0, 0),
        Offset(size.width, 0),
        [
          Colors.white.withOpacity(0.0),
          Colors.white.withOpacity(0.5),
          Colors.white.withOpacity(0.9), // Intense center
          Colors.white.withOpacity(0.5),
          Colors.white.withOpacity(0.0),
        ],
        [0.0, 0.2, 0.5, 0.8, 1.0],
      )
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.0
      ..strokeCap = StrokeCap.round;

    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(_TopLightningBorderPainter oldDelegate) =>
      oldDelegate.borderRadius != borderRadius;
}
