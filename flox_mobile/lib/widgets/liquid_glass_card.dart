import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'dart:io';

/// Flutter wrapper for the native Android LiquidGlassCard Composable.
/// Based on the SolidGlassShader by abduzeedo.
///
/// ## Features:
/// - Interactive draggable glass lens
/// - Real-time refraction with IOR control
/// - Specular highlights and bevel effects
/// - Chromatic aberration
/// - Animated liquid flow gradient
/// - GPU-accelerated blur
///
/// ## Usage:
/// ```dart
/// LiquidGlassCardWidget(
///   width: 300,
///   height: 200,
///   cornerRadius: 24,
///   blurIntensity: 25,
///   ior: 1.45,
///   highlightStrength: 0.9,
///   child: YourContent(),
/// )
/// ```
class LiquidGlassCardWidget extends StatelessWidget {
  /// Width of the glass card in logical pixels
  final double width;

  /// Height of the glass card in logical pixels
  final double height;

  /// Corner radius for rounded edges
  final double cornerRadius;

  /// Blur intensity for frosted glass effect (1-50)
  final double blurIntensity;

  /// Index of Refraction. 1.0 = no refraction, 1.45 = glass, 2.0 = diamond
  final double ior;

  /// Intensity of specular highlights (0.0 - 2.0)
  final double highlightStrength;

  /// Width of the edge bevel highlight (0.0 - 0.3)
  final double bevelWidth;

  /// Simulated glass thickness affecting distortion (0.0 - 1.0)
  final double thickness;

  /// Darkness of cast shadow (0.0 - 1.0)
  final double shadowIntensity;

  /// Color fringing strength (0.0 - 0.03)
  final double chromaticAberration;

  /// Speed of the liquid flow animation
  final double animationSpeed;

  /// Child widget to display inside the glass card
  final Widget? child;

  const LiquidGlassCardWidget({
    super.key,
    this.width = 300,
    this.height = 200,
    this.cornerRadius = 24,
    this.blurIntensity = 25,
    this.ior = 1.45,
    this.highlightStrength = 0.9,
    this.bevelWidth = 0.15,
    this.thickness = 0.6,
    this.shadowIntensity = 0.5,
    this.chromaticAberration = 0.01,
    this.animationSpeed = 1.0,
    this.child,
  });

  @override
  Widget build(BuildContext context) {
    // Only use native view on Android
    if (!Platform.isAndroid) {
      return _buildFallback();
    }

    return SizedBox(
      width: width,
      height: height,
      child: Stack(
        children: [
          // Native Android LiquidGlassCard with shader
          AndroidView(
            viewType: 'liquid_glass_card',
            creationParams: {
              'width': width,
              'height': height,
              'cornerRadius': cornerRadius,
              'blurIntensity': blurIntensity,
              'ior': ior,
              'highlightStrength': highlightStrength,
              'bevelWidth': bevelWidth,
              'thickness': thickness,
              'shadowIntensity': shadowIntensity,
              'chromaticAberration': chromaticAberration,
              'animationSpeed': animationSpeed,
            },
            creationParamsCodec: const StandardMessageCodec(),
          ),
          // Overlay child content
          if (child != null) Positioned.fill(child: child!),
        ],
      ),
    );
  }

  /// Fallback for non-Android platforms
  Widget _buildFallback() {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(cornerRadius),
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            Colors.white.withOpacity(0.15),
            Colors.white.withOpacity(0.05),
          ],
        ),
        border: Border.all(color: Colors.white.withOpacity(0.3), width: 1),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(shadowIntensity * 0.3),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(cornerRadius),
        child: child,
      ),
    );
  }
}

/// Navigation bar variant with optimized settings
class LiquidGlassNavBar extends StatelessWidget {
  final double height;
  final double cornerRadius;
  final double blurIntensity;
  final Widget? child;

  const LiquidGlassNavBar({
    super.key,
    this.height = 80,
    this.cornerRadius = 0,
    this.blurIntensity = 30,
    this.child,
  });

  @override
  Widget build(BuildContext context) {
    return LiquidGlassCardWidget(
      width: MediaQuery.of(context).size.width,
      height: height,
      cornerRadius: cornerRadius,
      blurIntensity: blurIntensity,
      ior: 1.3,
      highlightStrength: 0.5,
      bevelWidth: 0.1,
      thickness: 0.4,
      shadowIntensity: 0.3,
      chromaticAberration: 0.005,
      animationSpeed: 0.5,
      child: child,
    );
  }
}

/// Modal dialog variant with stronger effects
class LiquidGlassModal extends StatelessWidget {
  final double width;
  final double height;
  final double cornerRadius;
  final Widget? child;

  const LiquidGlassModal({
    super.key,
    this.width = 320,
    this.height = 400,
    this.cornerRadius = 32,
    this.child,
  });

  @override
  Widget build(BuildContext context) {
    return LiquidGlassCardWidget(
      width: width,
      height: height,
      cornerRadius: cornerRadius,
      blurIntensity: 35,
      ior: 1.5,
      highlightStrength: 1.0,
      bevelWidth: 0.18,
      thickness: 0.7,
      shadowIntensity: 0.6,
      chromaticAberration: 0.015,
      animationSpeed: 0.8,
      child: child,
    );
  }
}
