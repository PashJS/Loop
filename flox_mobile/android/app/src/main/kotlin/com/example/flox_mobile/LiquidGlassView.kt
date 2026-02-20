package com.example.flox_mobile

import android.content.Context
import android.graphics.RenderEffect
import android.graphics.RuntimeShader
import android.graphics.Shader
import android.os.Build
import android.view.View
import androidx.annotation.RequiresApi
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.graphics.*
import androidx.compose.ui.graphics.drawscope.drawIntoCanvas
import androidx.compose.ui.platform.ComposeView
import androidx.compose.ui.unit.dp
import io.flutter.plugin.platform.PlatformView
import kotlin.math.sin

/**
 * TRUE Liquid Glass with Refraction using RuntimeShader
 * 
 * This implements:
 * - Real-time background distortion (refraction)
 * - Depth with inner shadows
 * - Specular highlights for glass thickness
 * - Clean aesthetic (no rainbow)
 */
class LiquidGlassView(
    context: Context,
    private val viewHeight: Float,
    private val viewBorderRadius: Float
) : PlatformView {
    
    private val composeView = ComposeView(context).apply {
        setContent {
            LiquidGlassContent(viewHeight, viewBorderRadius)
        }
    }

    override fun getView(): View = composeView

    override fun dispose() {}
}

@Composable
fun LiquidGlassContent(height: Float, borderRadius: Float) {
    // Animation for subtle liquid movement
    val infiniteTransition = rememberInfiniteTransition(label = "liquid")
    val time by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(8000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "time"
    )

    // PERF: Compile shader ONCE and remember it. 
    val shader = remember {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            RuntimeShader("""
                uniform shader content;
                uniform float2 resolution;
                uniform float time;
                
                float noise(float2 p) {
                    return fract(sin(dot(p, float2(12.9898, 78.233))) * 43758.5453);
                }
                
                half4 main(float2 coord) {
                    float2 uv = coord / resolution;
                    float distortionStrength = 0.008;
                    float wave1 = sin(uv.y * 15.0 + time * 2.0) * distortionStrength;
                    float wave2 = sin(uv.x * 20.0 + time * 1.5) * distortionStrength;
                    float microNoise = (noise(uv * 100.0 + time * 0.1) - 0.5) * 0.002;
                    float2 distortedCoord = coord + float2(wave1 + microNoise, wave2 + microNoise);
                    half4 color = content.eval(distortedCoord);
                    float edgeDist = min(min(uv.x, 1.0 - uv.x), min(uv.y, 1.0 - uv.y));
                    if (edgeDist < 0.1) {
                        float aberration = (0.1 - edgeDist) * 0.003;
                        half4 r = content.eval(distortedCoord + float2(aberration, 0));
                        half4 b = content.eval(distortedCoord - float2(aberration, 0));
                        color = half4(r.r, color.g, b.b, color.a);
                    }
                    return color;
                }
            """.trimIndent())
        } else null
    }

    // PERF: Cache RenderEffect object. Updating uniforms on the shader 
    // will be reflected in the existing effect instance. 
    val glassEffect = remember(shader) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU && shader != null) {
            RenderEffect.createRuntimeShaderEffect(shader, "content").asComposeRenderEffect()
        } else null
    }

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(height.dp)
            .clip(RoundedCornerShape(borderRadius.dp))
    ) {
        // Layer 1: Refraction Effect
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU && glassEffect != null && shader != null) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(
                        Brush.radialGradient(
                            colors = listOf(
                                Color.White.copy(alpha = 0.15f),
                                Color.Transparent
                            ),
                            radius = 500f
                        )
                    )
                    .graphicsLayer {
                        // Update uniforms on cached shader. 
                        // Safety: Avoid division by zero if width/height is 0
                        val w = if (size.width > 0) size.width else 1f
                        val h = if (size.height > 0) size.height else 1f
                        
                        shader.setFloatUniform("time", time * 3.14159f * 2f)
                        shader.setFloatUniform("resolution", w, h)
                        
                        renderEffect = glassEffect
                    }
            )
        }

        // Layer 2: Frosted Blur
        // PERF: BlurEffect is also a native object. Caching it here.
        val blurEffect = remember {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                RenderEffect.createBlurEffect(25f, 25f, Shader.TileMode.CLAMP).asComposeRenderEffect()
            } else null
        }

        Box(
            modifier = Modifier
                .fillMaxSize()
                .graphicsLayer {
                    if (blurEffect != null) {
                        renderEffect = blurEffect
                    }
                }
                .background(Color.Black.copy(alpha = 0.3f))
        )

        // Layer 3: Depth & Glass Surface
        Box(
            modifier = Modifier
                .fillMaxSize()
                .drawWithContent {
                    drawContent()
                    
                    // Inner shadow (depth)
                    drawIntoCanvas { canvas ->
                        val paint = Paint().apply {
                            color = Color.Black.copy(alpha = 0.4f)
                            blendMode = BlendMode.Multiply
                        }
                        canvas.drawRoundRect(
                            left = 2f,
                            top = 2f,
                            right = size.width - 2f,
                            bottom = size.height - 2f,
                            radiusX = borderRadius,
                            radiusY = borderRadius,
                            paint = paint
                        )
                    }
                    
                    // Specular highlight (top edge)
                    val highlightBrush = Brush.verticalGradient(
                        0f to Color.White.copy(alpha = 0.3f),
                        0.15f to Color.Transparent,
                        1f to Color.Transparent
                    )
                    drawRect(highlightBrush)
                    
                    // Subtle bottom reflection
                    val bottomBrush = Brush.verticalGradient(
                        0f to Color.Transparent,
                        0.85f to Color.Transparent,
                        1f to Color.White.copy(alpha = 0.1f)
                    )
                    drawRect(bottomBrush)
                }
                .border(
                    width = 1.dp,
                    brush = Brush.linearGradient(
                        colors = listOf(
                            Color.White.copy(alpha = 0.4f),
                            Color.White.copy(alpha = 0.1f)
                        )
                    ),
                    shape = RoundedCornerShape(borderRadius.dp)
                )
        )
    }
}
