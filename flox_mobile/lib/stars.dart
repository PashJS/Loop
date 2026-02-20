import 'dart:math';
import 'package:flutter/material.dart';

// Define Star class for pre-computation optimization
class Star {
  final double x;
  final double yBase;
  final double speed;
  final double radius;
  final double opacity;

  Star({
    required this.x,
    required this.yBase,
    required this.speed,
    required this.radius,
    required this.opacity,
  });
}

// Generate stars once
List<Star> generateStars() {
  final rand = Random(999);
  return List.generate(80, (index) {
    return Star(
      x: rand.nextDouble(), // 0.0 to 1.0 (relative width)
      yBase: rand.nextDouble(), // 0.0 to 1.0 (relative height)
      speed: 0.2 + rand.nextDouble() * 0.5,
      radius: rand.nextDouble() * 1.2,
      opacity: 0.3 + rand.nextDouble() * 0.5,
    );
  });
}

class NebulaPainter extends CustomPainter {
  final double t;
  final List<Star> stars;

  NebulaPainter(this.t, this.stars);

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint();
    for (final star in stars) {
      final y = (star.yBase * size.height - t * star.speed * 300) % size.height;
      paint.color = Colors.white.withOpacity(star.opacity);
      canvas.drawCircle(Offset(star.x * size.width, y), star.radius, paint);
    }
  }

  @override
  bool shouldRepaint(NebulaPainter old) => true;
}
