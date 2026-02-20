#include <flutter/runtime_effect.glsl>

uniform vec2 uResolution;      // Size of the input bar (width, height)
uniform float uTime;           // Animation time
uniform vec2 uWidgetOffset;    // Absolute position of the widget on screen
uniform vec2 uScreenSize;      // Total screen size (for UV mapping)
uniform sampler2D uBackground; // Full screen background texture

out vec4 fragColor;

void main() {
    vec2 pos = FlutterFragCoord().xy; // Local coordinates in pixels
    vec2 globalPos = uWidgetOffset + pos;
    
    // Calculate normalized screen coordinates (0.0 to 1.0)
    vec2 screenUV = globalPos / uScreenSize;

    // 1. Refraction / Distortion
    // Create a subtle liquid wobble based on time and position
    float waveX = sin(screenUV.y * 12.0 + uTime * 1.5) * 0.003;
    float waveY = cos(screenUV.x * 20.0 + uTime * 1.2) * 0.003;
    vec2 distortion = vec2(waveX, waveY);

    // 2. Chromatic Aberration (RGB Split)
    // Sample the background at slightly offset positions for R, G, B
    // This gives the "thick glass" look at edges
    float r = texture(uBackground, screenUV + distortion * 2.5).r;
    float g = texture(uBackground, screenUV + distortion).g;
    float b = texture(uBackground, screenUV + distortion * 0.5).b;
    vec3 color = vec3(r, g, b);

    // 3. Frosted Filter (Desaturate + Brighten + Tint)
    // Convert to grayscale
    float gray = dot(color, vec3(0.299, 0.587, 0.114));
    // Mix original color with gray (0.0 = full color, 1.0 = gray)
    // 0.2 mix means mostly color, slight mute
    color = mix(color, vec3(gray), 0.2);
    
    // Add glass tint (very subtle cyan/blue)
    color += vec3(0.02, 0.04, 0.06);
    
    // Brighten (diffuse light)
    color = color * 0.9 + 0.1;

    // 4. Surface Imperfections / Highlights
    // Add top edge specular highlight (simulating light from above)
    // We use local UV for this (relative to the bar itself)
    vec2 localUV = pos / uResolution;
    float topHighlight = smoothstep(0.0, 1.0, 1.0 - abs(localUV.y - 0.0)) * 
                         smoothstep(0.5, 0.0, localUV.y); 
    color += vec3(0.15) * topHighlight;

    // Add noise/grain for realism (optional, kept simple for perf)
    
    // 5. Output
    // Ensure alpha is 1.0 inside the bar (we handle transparency via mixing above)
    // But wait, the bar itself should be slightly translucent? 
    // Actually, we are replacing the pixel with the refracted background pixel. 
    // We don't need alpha blending with "nothing", we are the glass.
    fragColor = vec4(color, 1.0);
}
