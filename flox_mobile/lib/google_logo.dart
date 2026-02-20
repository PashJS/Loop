import 'package:flutter/material.dart';
import 'dart:math';

class GoogleLogoPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = size.width * 0.15
      ..strokeCap = StrokeCap.round;

    // G shape is roughly 4 arcs
    final center = Offset(size.width / 2, size.height / 2);
    final radius = size.width * 0.4;

    // Blue
    paint.color = const Color(0xFF4285F4);
    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius),
      -pi / 4,
      -pi / 2,
      false,
      paint,
    );

    // Green
    paint.color = const Color(0xFF34A853);
    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius),
      -pi / 4.5,
      pi / 3,
      false,
      paint,
    );

    // Yellow
    paint.color = const Color(0xFFFBBC05);
    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius),
      pi / 10,
      pi / 4,
      false,
      paint,
    );

    // Red
    paint.color = const Color(0xFFEA4335);
    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius),
      pi / 2.5,
      pi / 2,
      false,
      paint,
    );

    // Middle bar
    paint.color = const Color(0xFF4285F4);
    paint.style = PaintingStyle.fill;
    // ... simplifies to a text or basic G for now to save complexity
  }

  @override
  bool shouldRepaint(old) => false;
}
