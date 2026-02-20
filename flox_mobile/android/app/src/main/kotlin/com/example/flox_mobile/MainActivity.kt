package com.example.flox_mobile

import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine

class MainActivity: FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        
        flutterEngine
            .platformViewsController
            .registry
            .registerViewFactory(
                "liquid_glass_view",
                LiquidGlassViewFactory()
            )
        
        flutterEngine
            .platformViewsController
            .registry
            .registerViewFactory(
                "liquid_glass_input_bar",
                LiquidGlassInputBarFactory()
            )
        
        // New: LiquidGlassCard - Full-featured glass shader with AGSL
        flutterEngine
            .platformViewsController
            .registry
            .registerViewFactory(
                "liquid_glass_card",
                LiquidGlassCardFactory()
            )
    }
}

