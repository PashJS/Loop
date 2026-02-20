import { motion } from 'motion/react';
import svgPaths from "../imports/svg-yq6i9hr9l";

export default function AnimatedLogo() {
  return (
    <div className="relative w-96 h-96">
      <svg className="block size-full" fill="none" preserveAspectRatio="xMidYMid meet" viewBox="0 0 390.443 390.534">
        <g id="Group 20">
          {/* Ellipse 16 - Vertical */}
          <motion.path
            d={svgPaths.p21815500}
            id="Ellipse 16"
            stroke="url(#paint0_linear_1_17)"
            strokeWidth="5"
            initial={{ pathLength: 0, opacity: 0, scale: 0.95 }}
            animate={{ pathLength: 1, opacity: 1, scale: 1 }}
            transition={{ duration: 1.2, ease: [0.34, 1.56, 0.64, 1], delay: 0.05 }}
            style={{ originX: "50%", originY: "50%" }}
          />
          
          {/* Ellipse 17 - Horizontal */}
          <motion.path
            d={svgPaths.pb7e4300}
            id="Ellipse 17"
            stroke="url(#paint1_linear_1_17)"
            strokeWidth="5"
            initial={{ pathLength: 0, opacity: 0, scale: 0.95 }}
            animate={{ pathLength: 1, opacity: 1, scale: 1 }}
            transition={{ duration: 1.2, ease: [0.34, 1.56, 0.64, 1], delay: 0.15 }}
            style={{ originX: "50%", originY: "50%" }}
          />
          
          {/* Ellipse 19 - Diagonal */}
          <motion.path
            d={svgPaths.p3a8b8600}
            id="Ellipse 19"
            stroke="url(#paint2_linear_1_17)"
            strokeWidth="5"
            initial={{ pathLength: 0, opacity: 0, scale: 0.95 }}
            animate={{ pathLength: 1, opacity: 1, scale: 1 }}
            transition={{ duration: 1.2, ease: [0.34, 1.56, 0.64, 1], delay: 0.1 }}
            style={{ originX: "50%", originY: "50%" }}
          />
          
          {/* Ellipse 18 - Diagonal */}
          <motion.path
            d={svgPaths.p1bfd0e40}
            id="Ellipse 18"
            stroke="url(#paint3_linear_1_17)"
            strokeWidth="5"
            initial={{ pathLength: 0, opacity: 0, scale: 0.95 }}
            animate={{ pathLength: 1, opacity: 1, scale: 1 }}
            transition={{ duration: 1.2, ease: [0.34, 1.56, 0.64, 1], delay: 0.2 }}
            style={{ originX: "50%", originY: "50%" }}
          />
          
          {/* Polygon 6 - Center Triangle */}
          <motion.path
            d={svgPaths.p1ecffe80}
            id="Polygon 6"
            stroke="url(#paint4_linear_1_17)"
            strokeWidth="5"
            initial={{ pathLength: 0, opacity: 0, rotate: -15 }}
            animate={{ pathLength: 1, opacity: 1, rotate: 0 }}
            transition={{ duration: 1, ease: [0.34, 1.56, 0.64, 1], delay: 0.3 }}
            style={{ originX: "50%", originY: "50%" }}
          />
        </g>
        <defs>
          <linearGradient gradientUnits="userSpaceOnUse" id="paint0_linear_1_17" x1="194.413" x2="196.03" y1="27.2686" y2="363.265">
            <stop stopColor="#00D9FF" />
            <stop offset="1" stopColor="#0070F0" />
          </linearGradient>
          <linearGradient gradientUnits="userSpaceOnUse" id="paint1_linear_1_17" x1="27.2309" x2="363.212" y1="197.031" y2="193.503">
            <stop stopColor="#00D9FF" />
            <stop offset="1" stopColor="#0070F0" />
          </linearGradient>
          <linearGradient gradientUnits="userSpaceOnUse" id="paint2_linear_1_17" x1="317.232" x2="73.2109" y1="310.754" y2="79.779">
            <stop stopColor="#00D9FF" />
            <stop offset="1" stopColor="#0070F0" />
          </linearGradient>
          <linearGradient gradientUnits="userSpaceOnUse" id="paint3_linear_1_17" x1="79.879" x2="310.564" y1="317.415" y2="73.1189">
            <stop stopColor="#00D9FF" />
            <stop offset="1" stopColor="#0070F0" />
          </linearGradient>
          <linearGradient gradientUnits="userSpaceOnUse" id="paint4_linear_1_17" x1="194.411" x2="194.053" y1="136.434" y2="261.099">
            <stop stopColor="#00C8FF" />
            <stop offset="1" stopColor="#006CB9" />
          </linearGradient>
        </defs>
      </svg>
    </div>
  );
}