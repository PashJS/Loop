package com.example.flox_mobile

import android.content.Context
import android.graphics.RenderEffect
import android.graphics.RuntimeShader
import android.graphics.Shader
import android.os.Build
import android.view.View
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.*
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.ComposeView
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.dp
import io.flutter.plugin.platform.PlatformView
import kotlin.math.cos
import kotlin.math.sin

// =============================================================================
// AGSL SHADER CODE - BASED ON abduzeedo/SolidGlassShader
// =============================================================================
//
// This AGSL (Android Graphics Shading Language) shader implements a physically-based
// glass lens effect with the following optical properties from the SolidGlassShader:
//
// 1. INDEX OF REFRACTION (IOR): Light bends when passing through glass materials.
//    Standard glass has IOR ~1.5, water ~1.33, air = 1.0
//
// 2. SPECULAR HIGHLIGHTS: Bright spots from light reflecting off the curved surface.
//    Uses a simplified Phong-like lighting model.
//
// 3. BEVEL EDGE: Glass lenses have chamfered edges that catch and reflect light,
//    creating a bright rim around the lens perimeter.
//
// 4. THICKNESS: Simulates the perceived thickness of the glass, affecting how
//    much the light bends (thicker = more distortion).
//
// 5. SHADOW: The glass casts a soft shadow on the content beneath it.
//
// 6. CHROMATIC ABERRATION: Different wavelengths of light (R/G/B) refract at
//    slightly different angles, creating rainbow fringes.
//
// 7. FROSTED BLUR: Simulates frosted/matte glass by sampling multiple nearby
//    pixels (actual blur done via RenderEffect for performance).
//
// The shader uses normalized UV coordinates (0-1) and calculates the distance
// from each fragment to the lens center to determine which optical effects apply.
// =============================================================================

private const val SOLID_GLASS_SHADER_SRC = """
    // =========================================================================
    // UNIFORMS - Parameters passed from Kotlin every frame
    // =========================================================================
    uniform shader content;           // The background content to refract
    uniform float2 resolution;        // Width x Height of the view in pixels
    uniform float2 lensCenter;        // Normalized lens center position (0-1)
    uniform float time;               // Animation time for liquid effects
    
    // Glass optical properties (adjustable via Kotlin)
    uniform float lensRadius;         // Size of glass lens (0.0 - 1.0)
    uniform float ior;                // Index of Refraction (1.0 - 2.0)
    uniform float highlightStrength;  // Specular highlight intensity (0.0 - 2.0)
    uniform float bevelWidth;         // Edge highlight width (0.0 - 0.3)
    uniform float thickness;          // Glass thickness / distortion strength
    uniform float shadowIntensity;    // Shadow darkness (0.0 - 1.0)
    uniform float chromaticAberration;// Color fringing strength (0.0 - 0.03)
    
    // =========================================================================
    // MAIN SHADER FUNCTION - Called for every pixel
    // =========================================================================
    half4 main(float2 fragCoord) {
        // Normalize coordinates to 0-1 range
        float2 uv = fragCoord / resolution;
        
        // Correct for aspect ratio to keep lens circular
        float aspectRatio = resolution.x / resolution.y;
        float2 correctedUV = float2(uv.x * aspectRatio, uv.y);
        float2 correctedCenter = float2(lensCenter.x * aspectRatio, lensCenter.y);
        
        // Distance from current pixel to lens center
        float dist = distance(correctedUV, correctedCenter);
        
        // =====================================================================
        // OUTSIDE THE LENS - Render shadow effect
        // =====================================================================
        if (dist > lensRadius) {
            half4 originalColor = content.eval(fragCoord);
            
            // Soft shadow beneath the lens (offset for directional light)
            float2 shadowOffset = float2(0.015, 0.025);
            float2 shadowCenter = float2(
                (lensCenter.x + shadowOffset.x) * aspectRatio,
                lensCenter.y + shadowOffset.y
            );
            float shadowDist = distance(correctedUV, shadowCenter);
            
            // Shadow fades out beyond lens radius
            float shadow = smoothstep(lensRadius * 1.5, lensRadius, shadowDist);
            shadow *= shadowIntensity * 0.35;
            
            return half4(originalColor.rgb * (1.0 - shadow), originalColor.a);
        }
        
        // =====================================================================
        // INSIDE THE LENS - Apply glass refraction effects
        // =====================================================================
        
        // Normalized distance (0 at center, 1 at edge)
        float normalizedDist = dist / lensRadius;
        
        // Calculate surface normal (approximating spherical lens)
        float2 normal = normalize(correctedUV - correctedCenter);
        
        // --- REFRACTION (Snell's Law Approximation) ---
        // Light bends more at edges than center; uses squared falloff
        float refractionPower = (ior - 1.0) * normalizedDist * normalizedDist * thickness;
        float2 refractOffset = normal * refractionPower * 0.12;
        
        // --- CHROMATIC ABERRATION ---
        // Split R/G/B slightly for color fringing
        float2 redOffset = refractOffset * (1.0 + chromaticAberration * 8.0);
        float2 greenOffset = refractOffset;
        float2 blueOffset = refractOffset * (1.0 - chromaticAberration * 8.0);
        
        // Sample each color channel at slightly different positions
        float2 redCoord = fragCoord + redOffset * resolution;
        float2 greenCoord = fragCoord + greenOffset * resolution;
        float2 blueCoord = fragCoord + blueOffset * resolution;
        
        half3 refractedColor = half3(
            content.eval(redCoord).r,
            content.eval(greenCoord).g,
            content.eval(blueCoord).b
        );
        
        // --- SPECULAR HIGHLIGHT (Phong-like) ---
        // Light source from top-left
        float2 lightDir = normalize(float2(-0.5, -0.7));
        float specular = pow(max(dot(-normal, lightDir), 0.0), 48.0);
        specular *= highlightStrength * (1.0 - normalizedDist * 0.5);
        
        // --- BEVEL EDGE HIGHLIGHT ---
        // Bright rim at the edge of the lens
        float bevelStart = 1.0 - bevelWidth;
        float bevelHighlight = 0.0;
        if (normalizedDist > bevelStart) {
            float bevelProgress = (normalizedDist - bevelStart) / bevelWidth;
            bevelHighlight = pow(bevelProgress, 1.5) * 0.6;
        }
        
        // --- CENTER GLOW ---
        // Thicker center appears slightly brighter
        float centerGlow = (1.0 - normalizedDist * normalizedDist) * 0.05;
        
        // --- LIQUID ANIMATION ---
        // Subtle flowing effect inside the glass
        float wave1 = sin(uv.x * 12.0 + time * 2.0) * 0.5 + 0.5;
        float wave2 = cos(uv.y * 10.0 - time * 1.5) * 0.5 + 0.5;
        float liquidEffect = wave1 * wave2 * 0.025 * (1.0 - normalizedDist);
        
        // --- COMBINE EFFECTS ---
        half3 finalColor = refractedColor;
        
        // Subtle glass tint (slight blue for realism)
        finalColor = mix(finalColor, half3(0.92, 0.95, 1.0), 0.04);
        
        // Add all highlights
        finalColor += half3(specular + bevelHighlight + centerGlow + liquidEffect);
        
        // Clamp to valid range
        finalColor = clamp(finalColor, half3(0.0), half3(1.0));
        
        return half4(finalColor, 1.0);
    }
"""

// =============================================================================
// DATA CLASS FOR SHADER PARAMETERS
// =============================================================================
/**
 * Configuration parameters for the LiquidGlassCard effect.
 * Based on the adjustable sliders from SolidGlassShader.
 *
 * @param ior Index of Refraction. 1.0 = no refraction, 1.5 = standard glass, 2.0 = diamond
 * @param highlightStrength Intensity of specular highlights (0.0 - 2.0)
 * @param bevelWidth Width of the edge bevel as fraction of lens radius (0.0 - 0.3)
 * @param thickness Simulated glass thickness affecting distortion (0.0 - 1.0)
 * @param shadowIntensity Darkness of cast shadow (0.0 - 1.0)
 * @param chromaticAberration Color fringing intensity (0.0 - 0.03)
 * @param animationSpeed Speed of liquid flow animation (0.0 - 2.0)
 */
data class GlassShaderParams(
    val ior: Float = 1.45f,
    val highlightStrength: Float = 0.9f,
    val bevelWidth: Float = 0.15f,
    val thickness: Float = 0.6f,
    val shadowIntensity: Float = 0.5f,
    val chromaticAberration: Float = 0.01f,
    val animationSpeed: Float = 1.0f
)

// =============================================================================
// MAIN COMPOSABLE: LiquidGlassCard
// =============================================================================
/**
 * A reusable Composable that renders content with a liquid glass lens effect.
 * Based on the SolidGlassShader by abduzeedo.
 *
 * ## HOW THE RuntimeShader IS APPLIED:
 * 1. The AGSL code string (SOLID_GLASS_SHADER_SRC) is compiled into a RuntimeShader
 * 2. RuntimeShader is created once using `remember {}` to avoid recompilation
 * 3. Every frame, uniform values are updated via `shader.setFloatUniform()`
 * 4. The shader is applied to the composable via `graphicsLayer { renderEffect = ... }`
 * 5. RenderEffect.createRuntimeShaderEffect() creates the effect, chained with blur
 *
 * ## HOW DRAG GESTURES UPDATE SHADER UNIFORMS:
 * 1. `pointerInput` with `detectDragGestures` captures finger movement
 * 2. Drag delta is converted to normalized 0-1 coordinates
 * 3. `lensPosition` state is updated, triggering recomposition
 * 4. New position is passed to shader via `setFloatUniform("lensCenter", x, y)`
 * 5. The shader immediately uses the new position for calculations
 *
 * ## HOW BLUR CREATES THE FROSTED EFFECT:
 * 1. RenderEffect.createBlurEffect() applies Gaussian blur to content
 * 2. This is GPU-accelerated and creates smooth, artifact-free blur
 * 3. The blur is chained BEFORE the shader using createChainEffect()
 * 4. The shader then adds glass highlights on top of the blurred content
 * 5. Combined effect = frosted glass with optical enhancements
 *
 * ## HOW THE ANIMATED GRADIENT PRODUCES A LIQUID LOOK:
 * 1. rememberInfiniteTransition() creates a continuously animating value
 * 2. animateFloat() produces a time value from 0 to 100 over animation duration
 * 3. This time value is passed to the shader as the "time" uniform
 * 4. Inside the shader, sin/cos waves use this time to create flowing patterns
 * 5. The wave effect is subtle and localized to the lens interior
 *
 * ## PERFORMANCE CONSIDERATIONS:
 * 1. Shader is compiled once and reused (expensive operation done once)
 * 2. Blur intensity is clamped to prevent excessive GPU load
 * 3. Only uniform updates happen each frame (cheap operation)
 * 4. For multiple cards, consider sharing a single RuntimeShader instance
 * 5. Reduce blur radius on low-end devices
 * 6. Animation uses LinearEasing for minimal interpolation overhead
 * 7. Large cards with high blur can impact frame rate - use sparingly
 *
 * @param modifier Standard Compose modifier
 * @param width Width of the glass card
 * @param height Height of the glass card
 * @param cornerRadius Rounded corner radius
 * @param blurIntensity Blur strength in dp (affects frosted appearance)
 * @param params Glass shader parameters (IOR, highlights, etc.)
 * @param enableDraggableLens If true, user can drag the glass lens around
 * @param lensRadius Size of the draggable lens (0.1 - 0.8)
 * @param content Content to display inside the glass card
 */
@Composable
fun LiquidGlassCard(
    modifier: Modifier = Modifier,
    width: Dp = 300.dp,
    height: Dp = 200.dp,
    cornerRadius: Dp = 24.dp,
    blurIntensity: Dp = 25.dp,
    params: GlassShaderParams = GlassShaderParams(),
    enableDraggableLens: Boolean = true,
    lensRadius: Float = 0.3f,
    content: @Composable BoxScope.() -> Unit = {}
) {
    val density = LocalDensity.current
    val blurPx = with(density) { blurIntensity.toPx() }
    
    // Track view size for shader resolution uniform
    var viewSize by remember { mutableStateOf(IntSize.Zero) }
    
    // =========================================================================
    // LENS POSITION STATE
    // Updated by drag gestures, triggers recomposition to update shader
    // =========================================================================
    var lensPosition by remember { mutableStateOf(Offset(0.5f, 0.5f)) }
    
    // =========================================================================
    // INFINITE ANIMATION FOR LIQUID EFFECT
    // Creates continuously updating time value for shader animation
    // =========================================================================
    val infiniteTransition = rememberInfiniteTransition(label = "liquidGlass")
    val animatedTime by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 100f,
        animationSpec = infiniteRepeatable(
            animation = tween(
                durationMillis = (80000 / params.animationSpeed).toInt(),
                easing = LinearEasing
            ),
            repeatMode = RepeatMode.Restart
        ),
        label = "time"
    )
    
    // =========================================================================
    // RUNTIME SHADER CREATION
    // Created once and remembered to avoid expensive recompilation
    // =========================================================================
    val shader = remember {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            try {
                RuntimeShader(SOLID_GLASS_SHADER_SRC)
            } catch (e: Exception) {
                null // Fallback to simple effect if shader fails
            }
        } else null
    }
    
    Box(
        modifier = modifier
            .width(width)
            .height(height)
            .clip(RoundedCornerShape(cornerRadius))
            .onSizeChanged { viewSize = it }
            // =========================================================================
            // DRAG GESTURE HANDLING
            // Captures touch input and updates lens position in real-time
            // =========================================================================
            .then(
                if (enableDraggableLens) {
                    Modifier.pointerInput(Unit) {
                        detectDragGestures { change, dragAmount ->
                            change.consume()
                            // Convert pixel drag to normalized 0-1 coordinates
                            val newX = (lensPosition.x + dragAmount.x / viewSize.width)
                                .coerceIn(lensRadius, 1f - lensRadius)
                            val newY = (lensPosition.y + dragAmount.y / viewSize.height)
                                .coerceIn(lensRadius, 1f - lensRadius)
                            lensPosition = Offset(newX, newY)
                        }
                    }
                } else Modifier
            )
    ) {
        // =====================================================================
        // ANIMATED GRADIENT BACKGROUND (Creates liquid flowing effect)
        // =====================================================================
        Box(
            modifier = Modifier
                .matchParentSize()
                .background(
                    Brush.linearGradient(
                        colors = listOf(
                            Color(0xFF1a1a2e),
                            Color(0xFF16213e),
                            Color(0xFF0f3460),
                            Color(0xFF1a1a2e)
                        ),
                        start = Offset(
                            sin(animatedTime * 0.05f).toFloat() * 200f,
                            cos(animatedTime * 0.03f).toFloat() * 200f
                        ),
                        end = Offset(
                            viewSize.width.toFloat() + cos(animatedTime * 0.04f).toFloat() * 200f,
                            viewSize.height.toFloat() + sin(animatedTime * 0.06f).toFloat() * 200f
                        )
                    )
                )
        )
        
        // =========================================================================
        // CACHED RENDER EFFECTS (PERF: Avoid allocating native objects every frame)
        // =========================================================================
        val blurEffect = remember(blurPx) {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                RenderEffect.createBlurEffect(
                    blurPx.coerceIn(1f, 50f),
                    blurPx.coerceIn(1f, 50f),
                    Shader.TileMode.CLAMP
                )
            } else null
        }
        
        val glassEffect = remember(shader, blurEffect) {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU && shader != null && blurEffect != null) {
                val shaderEffect = RenderEffect.createRuntimeShaderEffect(shader, "content")
                RenderEffect.createChainEffect(shaderEffect, blurEffect).asComposeRenderEffect()
            } else null
        }

        // =====================================================================
        // CONTENT LAYER WITH SHADER EFFECT
        // =====================================================================
        Box(
            modifier = Modifier
                .matchParentSize()
                .then(
                    if (glassEffect != null && shader != null) {
                        Modifier.graphicsLayer {
                            // =========================================================
                            // UPDATE SHADER UNIFORMS
                            // These values are sent to the GPU every frame
                            // =========================================================
                            val w = if (viewSize.width > 0) viewSize.width.toFloat() else 1f
                            val h = if (viewSize.height > 0) viewSize.height.toFloat() else 1f
                            
                            shader.setFloatUniform("resolution", w, h)
                            shader.setFloatUniform("lensCenter", 
                                lensPosition.x, 
                                lensPosition.y
                            )
                            shader.setFloatUniform("time", animatedTime)
                            shader.setFloatUniform("lensRadius", lensRadius)
                            shader.setFloatUniform("ior", params.ior)
                            shader.setFloatUniform("highlightStrength", params.highlightStrength)
                            shader.setFloatUniform("bevelWidth", params.bevelWidth)
                            shader.setFloatUniform("thickness", params.thickness)
                            shader.setFloatUniform("shadowIntensity", params.shadowIntensity)
                            shader.setFloatUniform("chromaticAberration", params.chromaticAberration)
                            
                            renderEffect = glassEffect
                        }
                    } else Modifier
                ),
            content = content
        )
        
        // =====================================================================
        // GLASS OVERLAY (Top highlights, borders)
        // =====================================================================
        Box(
            modifier = Modifier
                .matchParentSize()
                .drawWithContent {
                    drawContent()
                    
                    // Top edge highlight
                    drawRect(
                        Brush.verticalGradient(
                            0f to Color.White.copy(alpha = 0.3f),
                            0.08f to Color.White.copy(alpha = 0.05f),
                            0.15f to Color.Transparent
                        )
                    )
                    
                    // Bottom reflection
                    drawRect(
                        Brush.verticalGradient(
                            0.9f to Color.Transparent,
                            1f to Color.White.copy(alpha = 0.08f)
                        )
                    )
                }
                .border(
                    width = 1.dp,
                    brush = Brush.sweepGradient(
                        colors = listOf(
                            Color.White.copy(alpha = 0.4f),
                            Color.White.copy(alpha = 0.1f),
                            Color.White.copy(alpha = 0.4f),
                            Color.White.copy(alpha = 0.1f)
                        )
                    ),
                    shape = RoundedCornerShape(cornerRadius)
                )
        )
        
        // =====================================================================
        // LENS INDICATOR (Shows draggable lens position)
        // =====================================================================
        if (enableDraggableLens) {
            Box(
                modifier = Modifier
                    .offset(
                        x = with(density) { (lensPosition.x * viewSize.width - viewSize.width * lensRadius).toDp() },
                        y = with(density) { (lensPosition.y * viewSize.height - viewSize.height * lensRadius).toDp() }
                    )
                    .size(with(density) { (viewSize.width * lensRadius * 2).toDp() })
                    .border(
                        width = 2.dp,
                        color = Color.White.copy(alpha = 0.3f),
                        shape = RoundedCornerShape(50)
                    )
            )
        }
    }
}

// =============================================================================
// FALLBACK FOR ANDROID < 13
// =============================================================================
@Composable
fun LiquidGlassCardFallback(
    modifier: Modifier = Modifier,
    width: Dp = 300.dp,
    height: Dp = 200.dp,
    cornerRadius: Dp = 24.dp,
    content: @Composable BoxScope.() -> Unit = {}
) {
    Box(
        modifier = modifier
            .width(width)
            .height(height)
            .clip(RoundedCornerShape(cornerRadius))
            .background(
                Brush.verticalGradient(
                    colors = listOf(
                        Color.White.copy(alpha = 0.15f),
                        Color.White.copy(alpha = 0.05f)
                    )
                )
            )
            .background(Color.Black.copy(alpha = 0.25f))
            .border(
                width = 1.dp,
                color = Color.White.copy(alpha = 0.3f),
                shape = RoundedCornerShape(cornerRadius)
            ),
        content = content
    )
}

// =============================================================================
// PLATFORM VIEW WRAPPER (For Flutter Integration)
// =============================================================================
class LiquidGlassCardPlatformView(
    context: Context,
    private val viewWidth: Float,
    private val viewHeight: Float,
    private val viewCornerRadius: Float,
    private val viewBlurIntensity: Float,
    private val viewParams: GlassShaderParams
) : PlatformView {
    
    private val composeView = ComposeView(context).apply {
        setContent {
            LiquidGlassCard(
                width = viewWidth.dp,
                height = viewHeight.dp,
                cornerRadius = viewCornerRadius.dp,
                blurIntensity = viewBlurIntensity.dp,
                params = viewParams,
                enableDraggableLens = true,
                lensRadius = 0.3f
            )
        }
    }
    
    override fun getView(): View = composeView
    override fun dispose() {}
}
