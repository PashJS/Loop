package com.example.flox_mobile

import android.content.Context
import io.flutter.plugin.common.StandardMessageCodec
import io.flutter.plugin.platform.PlatformView
import io.flutter.plugin.platform.PlatformViewFactory

/**
 * Factory for creating LiquidGlassCardPlatformView instances from Flutter.
 * 
 * ## Usage from Flutter:
 * ```dart
 * AndroidView(
 *   viewType: 'liquid_glass_card',
 *   creationParams: {
 *     'width': 300.0,
 *     'height': 200.0,
 *     'cornerRadius': 24.0,
 *     'blurIntensity': 25.0,
 *     'ior': 1.45,
 *     'highlightStrength': 0.9,
 *     'bevelWidth': 0.15,
 *     'thickness': 0.6,
 *     'shadowIntensity': 0.5,
 *     'chromaticAberration': 0.01,
 *     'animationSpeed': 1.0,
 *   },
 *   creationParamsCodec: const StandardMessageCodec(),
 * )
 * ```
 */
class LiquidGlassCardFactory : PlatformViewFactory(StandardMessageCodec.INSTANCE) {
    
    override fun create(context: Context, viewId: Int, args: Any?): PlatformView {
        val params = args as? Map<*, *> ?: emptyMap<String, Any>()
        
        // Extract view dimensions
        val width = (params["width"] as? Double)?.toFloat() ?: 300f
        val height = (params["height"] as? Double)?.toFloat() ?: 200f
        val cornerRadius = (params["cornerRadius"] as? Double)?.toFloat() ?: 24f
        val blurIntensity = (params["blurIntensity"] as? Double)?.toFloat() ?: 25f
        
        // Extract shader parameters matching updated GlassShaderParams
        val shaderParams = GlassShaderParams(
            ior = (params["ior"] as? Double)?.toFloat() ?: 1.45f,
            highlightStrength = (params["highlightStrength"] as? Double)?.toFloat() ?: 0.9f,
            bevelWidth = (params["bevelWidth"] as? Double)?.toFloat() ?: 0.15f,
            thickness = (params["thickness"] as? Double)?.toFloat() ?: 0.6f,
            shadowIntensity = (params["shadowIntensity"] as? Double)?.toFloat() ?: 0.5f,
            chromaticAberration = (params["chromaticAberration"] as? Double)?.toFloat() ?: 0.01f,
            animationSpeed = (params["animationSpeed"] as? Double)?.toFloat() ?: 1.0f
        )
        
        return LiquidGlassCardPlatformView(
            context = context,
            viewWidth = width,
            viewHeight = height,
            viewCornerRadius = cornerRadius,
            viewBlurIntensity = blurIntensity,
            viewParams = shaderParams
        )
    }
}
