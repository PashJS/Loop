package com.example.flox_mobile

import android.content.Context
import android.graphics.RenderEffect
import android.graphics.RuntimeShader
import android.graphics.Shader
import android.os.Build
import android.view.View
import androidx.compose.animation.core.*
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.*
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.ComposeView
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.ViewCompositionStrategy
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import io.flutter.plugin.platform.PlatformView
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleOwner
import androidx.lifecycle.LifecycleRegistry
import androidx.lifecycle.setViewTreeLifecycleOwner
import androidx.savedstate.SavedStateRegistry
import androidx.savedstate.SavedStateRegistryController
import androidx.savedstate.SavedStateRegistryOwner
import androidx.savedstate.setViewTreeSavedStateRegistryOwner

class LiquidGlassInputBar(
    context: Context,
    private val viewHeight: Float,
    private val viewBorderRadius: Float
) : PlatformView {
    
    private var lifecycleOwner: MyLifecycleOwner? = null
    
    private val composeView = ComposeView(context).apply {
        setViewCompositionStrategy(ViewCompositionStrategy.DisposeOnDetachedFromWindow)
        
        lifecycleOwner = MyLifecycleOwner()
        lifecycleOwner?.attachToView(this)
        lifecycleOwner?.handleLifecycleEvent(Lifecycle.Event.ON_CREATE)
        lifecycleOwner?.handleLifecycleEvent(Lifecycle.Event.ON_START)
        lifecycleOwner?.handleLifecycleEvent(Lifecycle.Event.ON_RESUME)

        setContent {
            // Error boundary logic inside composable if needed, but not try-catch block around invocation
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                LiquidGlassContent(viewHeight.dp, viewBorderRadius.dp)
            } else {
                FallbackGlassContent(viewHeight.dp, viewBorderRadius.dp)
            }
        }
    }

    override fun getView(): View = composeView
    
    override fun dispose() {
        lifecycleOwner?.handleLifecycleEvent(Lifecycle.Event.ON_PAUSE)
        lifecycleOwner?.handleLifecycleEvent(Lifecycle.Event.ON_STOP)
        lifecycleOwner?.handleLifecycleEvent(Lifecycle.Event.ON_DESTROY)
        lifecycleOwner = null
    }
    
    private class MyLifecycleOwner : LifecycleOwner, SavedStateRegistryOwner {
        private val lifecycleRegistry = LifecycleRegistry(this)
        private val savedStateRegistryController = SavedStateRegistryController.create(this)

        init {
            savedStateRegistryController.performRestore(null)
        }

        override val lifecycle: Lifecycle
            get() = lifecycleRegistry

        override val savedStateRegistry: SavedStateRegistry
            get() = savedStateRegistryController.savedStateRegistry

        fun handleLifecycleEvent(event: Lifecycle.Event) {
            lifecycleRegistry.handleLifecycleEvent(event)
        }
        
        fun attachToView(view: View) {
            try {
                view.setViewTreeLifecycleOwner(this)
                view.setViewTreeSavedStateRegistryOwner(this)
            } catch (e: Exception) {
                // Fallback or ignore
            }
        }
    }
}

@Composable
fun LiquidGlassContent(height: Dp, cornerRadius: Dp) {
    val density = LocalDensity.current
    
    // Animation
    val infiniteTransition = rememberInfiniteTransition(label = "liquid")
    val time by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 100f,
        animationSpec = infiniteRepeatable(
            animation = tween(20000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "time"
    )
    
    val shader = remember {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            try {
                RuntimeShader("""
                    uniform shader content;
                    uniform float2 resolution;
                    uniform float time;
                    half4 main(float2 coord) {
                        float2 uv = coord / resolution;
                        float waveX = sin(uv.y * 12.0 + time * 1.5) * 0.005;
                        float waveY = cos(uv.x * 20.0 + time * 1.2) * 0.005;
                        float2 distortedCoord = coord + float2(waveX, waveY) * resolution;
                        half4 color = content.eval(distortedCoord);
                        color.r = content.eval(distortedCoord + float2(3.0, 0.0)).r;
                        color.b = content.eval(distortedCoord - float2(3.0, 0.0)).b;
                        return color;
                    }
                """.trimIndent())
            } catch (e: Exception) {
                null
            }
        } else null
    }
    
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(height)
            .clip(RoundedCornerShape(cornerRadius))
    ) {
         if (shader != null && Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
             Box(
                 modifier = Modifier
                     .matchParentSize()
                     .graphicsLayer {
                         val w = if (size.width > 1f) size.width else 100f
                         val h = if (size.height > 1f) size.height else 100f
                         shader.setFloatUniform("resolution", w, h)
                         shader.setFloatUniform("time", time)
                         if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                             val blur = RenderEffect.createBlurEffect(30f, 30f, Shader.TileMode.CLAMP)
                             val shade = RenderEffect.createRuntimeShaderEffect(shader, "content")
                             renderEffect = RenderEffect.createChainEffect(shade, blur).asComposeRenderEffect()
                         }
                         alpha = 0.99f
                         clip = true
                     }
                     .background(Color.White.copy(alpha = 0.05f))
             )
         } else {
             FallbackGlassContent(height, cornerRadius)
         }
         
         GlassOverlays(cornerRadius)
    }
}

@Composable
fun GlassOverlays(cornerRadius: Dp) {
    androidx.compose.foundation.Canvas(modifier = Modifier.fillMaxSize()) {
         drawRect(
            Brush.verticalGradient(
                0f to Color.White.copy(alpha = 0.35f),
                0.2f to Color.Transparent,
                1f to Color.Transparent
            )
        )
        
        drawRoundRect(
            color = Color.White.copy(alpha = 0.2f),
            style = Stroke(width = 2f),
            cornerRadius = CornerRadius(cornerRadius.toPx())
        )
    }
}

@Composable
fun FallbackGlassContent(height: Dp, cornerRadius: Dp) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(height)
            .clip(RoundedCornerShape(cornerRadius))
            .background(Color.Black.copy(alpha = 0.3f))
            .border(1.dp, Color.White.copy(alpha = 0.2f), RoundedCornerShape(cornerRadius))
    )
}
