package com.floxwatch.reco

import io.ktor.server.application.*
import io.ktor.server.response.*
import io.ktor.server.routing.*
import java.time.Instant
import kotlin.math.min

// ==========================================
// 1. Data Models
// ==========================================

data class VideoCandidate(
    val videoId: Long,
    val authorId: Long,
    val durationMs: Long,
    val uploadTime: Instant,
    val hashtags: List<String>,
    val regionCode: String,
    // Pre-computed stats
    val likeCount: Int,
    val shareCount: Int,
    val avgCompletionRate: Double
)

data class UserProfile(
    val userId: Long,
    val regionCode: String,
    val followedAuthors: Set<Long>,
    val interestWeights: Map<String, Double> // Hashtag -> Weight (e.g., "comedy" -> 1.5)
)

data class FeedResponse(
    val feedId: String,
    val videos: List<VideoCandidate>,
    val nextCursor: String?
)

// ==========================================
// 2. Scoring Logic (Algorithm)
// ==========================================

class ScoringEngine {
    
    // Configurable weights for personalization
    private val W_COMPLETION_RATE_GLOBAL = 40.0 // Weight on global video performance
    private val W_INTEREST_MATCH = 30.0         // Weight on user's specific interests
    private val W_KEYWORD_MATCH = 10.0
    private val W_REGION_BOOST = 5.0
    private val W_RECENCY = 15.0
    
    /**
     * Calculates a personalized score for a video for a specific user.
     * Higher score = higher position in feed.
     */
    fun score(video: VideoCandidate, user: UserProfile): Double {
        var score = 0.0

        // A. Global Quality Signal (0-40 points)
        // If the video is generally good (high completion rate), boost it
        score += (video.avgCompletionRate * W_COMPLETION_RATE_GLOBAL)

        // B. Personalization: Interest Match (0-30 points)
        // Check if video hashtags match user's high-weight interests
        var interestScore = 0.0
        var matchCount = 0
        for (tag in video.hashtags) {
            val userWeight = user.interestWeights.getOrDefault(tag, 1.0)
            if (userWeight > 1.0) {
                interestScore += (userWeight * 5.0) // Boost for high interest
                matchCount++
            } else if (userWeight < 0.5) {
                interestScore -= 10.0 // Penalize disliked topics
            }
        }
        // Normalize interest score
        score += min(interestScore, W_INTEREST_MATCH)

        // C. Region Affinity (0-5 points)
        if (video.regionCode == user.regionCode) {
            score += W_REGION_BOOST
        }

        // D. Creator Affinity (Followed)
        if (user.followedAuthors.contains(video.authorId)) {
            score += 25.0 // Direct injection of followed content
        }

        // E. Recency Decay (Freshness)
        // ... (Simple logic: newer videos get small boost)

        return score
    }
}

// ==========================================
// 3. User Interest Vector System
// ==========================================

class InterestVectorSystem {
    
    // Updates user profile based on realtime behavior
    fun updateUserWeights(currentProfile: UserProfile, interaction: InteractionEvent): Map<String, Double> {
        val newWeights = currentProfile.interestWeights.toMutableMap()
        val decayFactor = 0.99 // Slight decay for all other interests to keep map fresh
        
        // 1. Decay existing
        // (In production, this is done periodically, not every request)
        
        // 2. Apply updates based on this video's tags
        val sentiment = calculateSentiment(interaction)
        
        interaction.videoHashtags.forEach { tag ->
            val current = newWeights.getOrDefault(tag, 1.0)
            val delta = when {
                sentiment > 0 -> 0.1 // Small increment for like/watch
                sentiment > 1 -> 0.3 // Big increment for completion/share
                sentiment < 0 -> -0.2 // Decrement for skip
                else -> 0.0
            }
            newWeights[tag] = (current + delta).coerceIn(0.1, 5.0) // Clamp weights
        }
        
        return newWeights
    }

    private fun calculateSentiment(event: InteractionEvent): Int {
        if (event.isFastSwipe) return -1
        if (event.percentWatched < 0.10) return -1
        if (event.isShared || event.percentWatched > 0.90) return 2
        if (event.isLiked || event.percentWatched > 0.50) return 1
        return 0
    }
}

// ==========================================
// 4. API Controller / Service
// ==========================================

data class InteractionEvent(
    val userId: Long,
    val videoId: Long,
    val videoHashtags: List<String>,
    val watchTimeMs: Long,
    val durationMs: Long,
    val isLiked: Boolean,
    val isShared: Boolean,
    val isFastSwipe: Boolean // < 1s view
) {
    val percentWatched: Double get() = if (durationMs > 0) watchTimeMs.toDouble() / durationMs else 0.0
}

class FeedService(
    private val scoringEngine: ScoringEngine,
    private val vectorSystem: InterestVectorSystem
    // Injected DB repos and Redis client would go here
) {
    
    // Called when app requests /api/feed
    fun getPersonalizedFeed(userId: Long, page: Int): List<VideoCandidate> {
        // 1. Fetch User Profile (Cached in Redis)
        val userProfile = fetchUserProfile(userId)

        // 2. Fetch Candidates
        //    a) Fresh: Recent uploads (Last 24h)
        //    b) Trending: High global engagement
        //    c) Fallback: Random (Diversity)
        val candidates = fetchCandidates(limit = 200)

        // 3. Score & Rank
        val ranked = candidates
            .map { it to scoringEngine.score(it, userProfile) }
            .sortedByDescending { it.second } // Sort by score
            .map { it.first }
            .take(20) // Return top 20

        // 4. Async: Pre-calculate next page and cache in Redis for "Instant Load"
        
        return ranked
    }

    private fun fetchUserProfile(id: Long): UserProfile {
        // Mock DB fetch
        return UserProfile(id, "US", setOf(101, 102), mapOf("comedy" to 1.5, "dance" to 1.2))
    }

    private fun fetchCandidates(limit: Int): List<VideoCandidate> {
        // Mock DB fetch
        return listOf() 
    }
}
