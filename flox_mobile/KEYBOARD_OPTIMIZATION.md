# Keyboard Animation Performance Optimization - v2

## Goal
Make Flutter UI elements animate smoothly at 60 FPS during keyboard open/close by:
1. Preventing full layout rebuilds during MediaQuery.viewInsets changes
2. Isolating keyboard-responsive widgets from heavy content
3. Using Stack + Positioned instead of Column for layout
4. Eliminating expensive effects during keyboard movement

---

## Architecture: Stack + Positioned Pattern

### Before (Problematic)
```dart
Scaffold(
  resizeToAvoidBottomInset: true,  // Triggers full layout rebuild
  body: Column(
    children: [
      Header(),           // Rebuilds on keyboard
      Expanded(Messages()), // Rebuilds on keyboard  
      InputBar(),         // Rebuilds on keyboard
    ],
  ),
)
```

### After (Optimized)
```dart
Scaffold(
  resizeToAvoidBottomInset: false,  // We handle keyboard manually
  body: Stack(
    children: [
      // STATIC LAYER - Never rebuilds during keyboard
      Positioned(top: X, left: 0, right: 0, child: RepaintBoundary(Header)),
      Positioned(top: X, left: 0, right: 0, bottom: Y, child: RepaintBoundary(Messages)),
      
      // KEYBOARD-RESPONSIVE LAYER - Only this rebuilds
      _KeyboardInsetWidget(child: RepaintBoundary(InputBar)),
    ],
  ),
)
```

---

## Key Components

### 1. `_KeyboardInsetWidget` (chat_detail_page.dart)
Isolated widget that ONLY rebuilds when keyboard height changes:
```dart
class _KeyboardInsetWidget extends StatelessWidget {
  final Widget child;
  const _KeyboardInsetWidget({required this.child});

  @override
  Widget build(BuildContext context) {
    final keyboardHeight = MediaQuery.viewInsetsOf(context).bottom;
    final safePadding = MediaQuery.viewPaddingOf(context).bottom;
    
    return Positioned(
      left: 0,
      right: 0,
      bottom: keyboardHeight > 0 ? keyboardHeight : safePadding,
      child: child,
    );
  }
}
```

### 2. `_OnboardingContentLayer` (main.dart)
Similar pattern for onboarding flow with forms:
```dart
class _OnboardingContentLayer extends StatelessWidget {
  final Widget child;
  const _OnboardingContentLayer({required this.child});

  @override
  Widget build(BuildContext context) {
    final keyboardHeight = MediaQuery.viewInsetsOf(context).bottom;
    return Positioned(
      top: 0, left: 0, right: 0,
      bottom: keyboardHeight,
      child: child,
    );
  }
}
```

---

## Changes Made

### `lib/chat_detail_page.dart`
| Change | Purpose |
|--------|---------|
| Added `_kInputBarHeight = 62.0` constant | Fixed height for predictable layout |
| Created `_KeyboardInsetWidget` | Isolates MediaQuery.viewInsetsOf dependency |
| `resizeToAvoidBottomInset: false` | Prevent Scaffold from doing layout rebuilds |
| Stack + Positioned layout | Header and messages are static, only input bar moves |
| `RepaintBoundary` on all layers | Isolate GPU repaints |
| Fixed input bar height | Eliminates height animation during keyboard |
| Solid colors (no `.withOpacity()`) | Faster rendering during movement |

### `lib/main.dart`
| Change | Purpose |
|--------|---------|
| Created `_OnboardingContentLayer` | Isolates keyboard dependency for forms |
| Removed nested Scaffold | Eliminates double layout pass |
| `View.of(context).viewInsets` for detection | Doesn't trigger rebuilds |
| `RepaintBoundary` on star animation | Isolates background repaints |

### Other Files
| File | Change |
|------|--------|
| `chats_page.dart` | RepaintBoundary on star animation |
| `home_page.dart` | RepaintBoundary on star animation |
| `AndroidManifest.xml` | Enabled Impeller |
| `Info.plist` | Enabled Impeller |

---

## What to AVOID During Keyboard Animation

### ❌ Layout Effects
- `AnimatedPadding` - causes layout animation
- `AnimatedPositioned` - causes layout animation  
- `AnimatedContainer` - causes layout animation
- Column/Row resize - triggers full layout pass

### ❌ GPU-Intensive Effects
- `BackdropFilter` (blur) - extremely expensive
- `BoxShadow` with spread - recomputes every frame
- `.withOpacity()` calls in paint - creates new objects
- Multiple overlapping decorations

### ✅ Recommended Patterns
- `Positioned` with `bottom:` value - only position changes
- Solid colors (`const Color(0xFF...)`) - no allocation
- `RepaintBoundary` - isolates repaint regions
- Fixed widget heights - no layout recalculation

---

## Performance Metrics Target

| Metric | Target | Where |
|--------|--------|-------|
| Build time | < 8ms | Frame timing in DevTools |
| Raster time | < 8ms | Frame timing in DevTools |
| Total frame | < 16ms | Both combined for 60 FPS |
| Widget rebuilds | 1-2 | Only keyboard-aware widgets |

---

## Profiling Commands

```bash
# Run in profile mode (required for accurate metrics)
flutter run --profile

# With performance overlay
flutter run --profile --trace-skia

# Check for jank with DevTools
# Open the URL printed in console
```

### In DevTools:
1. Go to **Performance** tab
2. Click **Record**
3. Open/close keyboard multiple times
4. Click **Stop**
5. Check for red bars (jank frames)
6. Verify "Build" and "Raster" both < 8ms

---

## Testing Checklist

- [ ] Profile on Android emulator with Impeller
- [ ] Profile on iOS simulator  
- [ ] Profile on real Android device (budget phone)
- [ ] Profile on real iOS device
- [ ] Verify no red frames during keyboard animation
- [ ] Verify message list doesn't repaint during keyboard
- [ ] Verify header doesn't rebuild during keyboard
- [ ] Verify input bar position updates at 60 FPS
