package com.example.flox_mobile

import android.content.Context
import io.flutter.plugin.common.StandardMessageCodec
import io.flutter.plugin.platform.PlatformView
import io.flutter.plugin.platform.PlatformViewFactory

class LiquidGlassViewFactory : PlatformViewFactory(StandardMessageCodec.INSTANCE) {
    override fun create(context: Context, viewId: Int, args: Any?): PlatformView {
        val params = args as? Map<String, Any> ?: mapOf()
        val height = (params["height"] as? Number)?.toFloat() ?: 56f
        val borderRadius = (params["borderRadius"] as? Number)?.toFloat() ?: 24f
        
        return LiquidGlassView(context, height, borderRadius)
    }
}
