#!/usr/bin/env python3
"""
Advanced Prescriptive Analytics Engine for FeedbackIQ
Uses statistical analysis, pattern recognition, and ML-inspired heuristics
to generate highly accurate and actionable business insights.

This script can be called from PHP or run standalone for analysis.
"""

import json
import sys
from datetime import datetime, timedelta
from typing import Dict, List, Any, Optional
import math


class FeedbackAnalyticsEngine:
    """Advanced analytics engine for feedback data."""
    
    def __init__(self):
        self.recommendations = []
        
    def _calculate_priority_score(self, rating: float, volume: int, confidence: float, 
                               volatility: float = 0, trend_impact: float = 0) -> float:
        """
        Calculate a comprehensive priority score based on multiple factors.
        
        Args:
            rating: Average rating (1-5)
            volume: Number of feedback responses
            confidence: Statistical confidence (0-1)
            volatility: Rating volatility/standard deviation
            trend_impact: Negative trend impact (-1 to 1)
            
        Returns:
            Priority score (0-100, higher = more urgent)
        """
        # Base score from rating (inverted: lower rating = higher score)
        rating_score = (5 - rating) * 20  # 0-80 points
        
        # Volume factor (more volume = higher priority)
        volume_factor = min(2.0, volume / 50)  # Cap at 2x multiplier
        volume_score = volume_factor * 15  # 0-30 points
        
        # Confidence factor (higher confidence = higher priority)
        confidence_score = confidence * 10  # 0-10 points
        
        # Volatility factor (higher volatility = higher priority)
        volatility_score = min(15, volatility * 10)  # 0-15 points
        
        # Trend impact factor
        trend_score = abs(trend_impact) * 5  # 0-5 points
        
        # Calculate total score
        total_score = rating_score + volume_score + confidence_score + volatility_score + trend_score
        
        # Normalize to 0-100 scale
        normalized_score = min(100, max(0, total_score))
        
        return normalized_score
    
    def _get_priority_from_score(self, score: float) -> str:
        """
        Convert priority score to priority level.
        
        Args:
            score: Priority score (0-100)
            
        Returns:
            Priority level ('critical', 'high', 'medium', 'low')
        """
        if score >= 80:
            return 'critical'
        elif score >= 60:
            return 'high'
        elif score >= 40:
            return 'medium'
        else:
            return 'low'
    
    def analyze(self, data: Dict[str, Any]) -> List[Dict[str, str]]:
        """
        Main analysis method that orchestrates all analytics.
        
        Args:
            data: Dictionary containing feedback data
            
        Returns:
            List of recommendation dictionaries
        """
        self.recommendations = []
        
        # Extract data
        total_feedback = data.get('total_feedback', 0)
        avg_rating = data.get('avg_rating', 0)
        recent_feedback = data.get('recent_feedback', 0)
        distribution = data.get('distribution', {})
        categories = data.get('categories', [])
        trend_data = data.get('trend_data', [])
        
        # Store trend data for performance analysis
        self._trend_data = trend_data
        
        # Run analysis modules
        if total_feedback > 0:
            self._analyze_performance_tier(avg_rating, total_feedback)
            self._analyze_rating_distribution(distribution, total_feedback)
            self._analyze_category_portfolio(categories, total_feedback)
            self._analyze_trend_momentum(trend_data)
            self._analyze_engagement_health(recent_feedback, total_feedback)
            self._analyze_service_quality_matrix(categories)
            self._analyze_statistical_confidence(total_feedback)
            
        # Sort by priority
        priority_order = {'critical': 0, 'high': 1, 'medium': 2, 'low': 3}
        self.recommendations.sort(key=lambda x: priority_order.get(x['priority'], 4))
        
        return self.recommendations
    
    def _analyze_performance_tier(self, avg_rating: float, total_feedback: int):
        """Determine performance tier with comprehensive priority scoring."""
        confidence = min(1.0, total_feedback / 100)
        
        # Calculate trend impact from recent data if available
        trend_data = self._get_trend_data()
        trend_impact = 0
        if trend_data and len(trend_data) >= 10:
            recent_avg = sum(d['avg_rating'] for d in trend_data[-7:]) / 7
            previous_avg = sum(d['avg_rating'] for d in trend_data[-14:-7]) / 7
            trend_impact = (recent_avg - previous_avg) / 5.0  # Normalize to -1 to 1 scale
        
        # Calculate volatility if multiple ratings exist
        volatility = 0
        if hasattr(self, '_rating_distribution') and len(self._rating_distribution) > 1:
            ratings = list(self._rating_distribution.keys())
            counts = list(self._rating_distribution.values())
            total = sum(counts)
            if total > 0:
                variance = sum(c * ((r - sum(r * c for r, c in zip(ratings, counts)) / total) ** 2 for r, c in zip(ratings, counts)) / total)
                volatility = math.sqrt(variance) / 5.0  # Normalize to 0-3 scale
        
        # Calculate comprehensive priority score
        priority_score = self._calculate_priority_score(
            rating=avg_rating,
            volume=total_feedback,
            confidence=confidence,
            volatility=volatility,
            trend_impact=trend_impact
        )
        
        # Determine priority level from score
        priority = self._get_priority_from_score(priority_score)
        
        if avg_rating >= 4.7 and total_feedback >= 100:
            self._add_recommendation(
                type='success',
                title='World-Class Performance',
                description=f'Your business achieves exceptional ratings ({round(avg_rating, 2)}/5.0) with high volume ({total_feedback} responses). You\'re in the top 5% of businesses. Consider pursuing industry awards or certifications.',
                priority='low',
                metric_impact='overall_excellence'
            )
        elif avg_rating >= 4.5 and total_feedback >= 50:
            self._add_recommendation(
                type='success',
                title='Outstanding Performance',
                description=f'Excellent ratings ({round(avg_rating, 2)}/5.0) with solid feedback volume. Your customer satisfaction is a competitive advantage. Leverage testimonials in marketing.',
                priority='low',
                metric_impact='overall_strong'
            )
        elif avg_rating >= 4.0 and total_feedback >= 30:
            self._add_recommendation(
                type='success',
                title='Strong Performance',
                description=f'Consistently good ratings ({round(avg_rating, 2)}/5.0) indicate reliable customer satisfaction. Focus on maintaining standards and gathering more feedback to reach excellence tier.',
                priority='low',
                metric_impact='overall_good'
            )
        elif avg_rating >= 3.5:
            self._add_recommendation(
                type='info',
                title='Good but Room for Improvement',
                description=f'Your ratings ({round(avg_rating, 2)}/5.0) are above average but there\'s opportunity to reach excellence. Target specific improvement areas to gain 0.5-1.0 rating points.',
                priority=priority,  # Use calculated priority
                metric_impact='overall_moderate'
            )
        elif avg_rating >= 3.0:
            self._add_recommendation(
                type='warning',
                title='Performance Needs Attention',
                description=f'Average rating ({round(avg_rating, 2)}/5.0) suggests inconsistent customer experiences. Implement systematic improvements: staff training, process optimization, and quality controls.',
                priority=priority,  # Use calculated priority
                metric_impact='overall_concern'
            )
        else:
            self._add_recommendation(
                type='warning',
                title='Performance Needs Attention',
                description=f'Low ratings ({round(avg_rating, 2)}/5.0) indicate systemic issues requiring immediate action. Conduct root cause analysis, implement emergency improvements, and consider external consultation.',
                priority=priority,  # Use calculated priority
                metric_impact='overall_concern'
            )
    
    def _get_trend_data(self):
        """Helper method to extract trend data for analysis."""
        # This would be populated by the main analyze method
        return getattr(self, '_trend_data', [])
    
    def _analyze_rating_distribution(self, distribution: Dict[int, int], total_feedback: int):
        """Analyze rating patterns using statistical measures."""
        if len(distribution) < 3:
            return
            
        ratings = list(distribution.keys())
        counts = list(distribution.values())
        
        # Calculate metrics
        mean = sum(r * c for r, c in distribution.items()) / total_feedback
        
        # Variance and standard deviation
        variance = sum(c * (r - mean) ** 2 for r, c in distribution.items()) / total_feedback
        std_dev = math.sqrt(variance)
        
        # Polarization analysis (bimodal check)
        low_count = sum(c for r, c in distribution.items() if r <= 2)
        mid_count = sum(c for r, c in distribution.items() if r == 3)
        high_count = sum(c for r, c in distribution.items() if r >= 4)
        
        low_pct = low_count / total_feedback
        mid_pct = mid_count / total_feedback
        high_pct = high_count / total_feedback
        
        # Check for polarization (U-shaped distribution)
        if (low_pct > 0.25 and high_pct > 0.45 and mid_pct < 0.15):
            self._add_recommendation(
                type='info',
                title='Polarized Customer Opinions',
                description=f'Customers have strong divided opinions: {round(low_pct*100)}% very dissatisfied, {round(high_pct*100)}% very satisfied, only {round(mid_pct*100)}% neutral. This "love it or hate it" pattern suggests your service excels for some but misses for others. Identify differentiating factors.',
                priority='medium',
                metric_impact='polarization'
            )
        
        # High negative feedback rate
        if low_pct > 0.25:
            self._add_recommendation(
                type='warning',
                title='High Negative Feedback Rate',
                description=f'{round(low_pct*100)}% of feedback is negative (1-2 stars), exceeding healthy threshold of 20%. Each negative review likely represents multiple unvoiced complaints. Investigate root causes immediately.',
                priority='high',
                metric_impact='negative_rate'
            )
        
        # Consistency check
        if std_dev < 0.8 and len(distribution) > 1:
            self._add_recommendation(
                type='success',
                title='Consistent Customer Experience',
                description=f'Low rating variance (σ={round(std_dev, 2)}) indicates you deliver highly consistent service. This reliability reduces customer anxiety and builds trust—a valuable competitive advantage.',
                priority='low',
                metric_impact='consistency'
            )
        elif std_dev > 1.5:
            self._add_recommendation(
                type='info',
                title='Inconsistent Experience Quality',
                description=f'High rating variance (σ={round(std_dev, 2)}) suggests unpredictable customer experiences. Standardize processes, improve training, and implement quality checks to reduce variability.',
                priority='medium',
                metric_impact='inconsistency'
            )
    
    def _analyze_category_portfolio(self, categories: List[Dict], total_feedback: int):
        """Deep category analysis using portfolio management approach."""
        for cat in categories:
            category_name = cat['category'].title()
            count = cat['count']
            avg_rating = cat['avg_rating']
            percentage = (count / total_feedback) * 100
            
            # Strategic positioning
            if count > (total_feedback * 0.30) and avg_rating < 3.0:
                self._add_recommendation(
                    type='warning',
                    title=f'Critical: {category_name} Crisis',
                    description=f'{category_name} represents {round(percentage)}% of feedback (high strategic importance) but has critically low ratings ({round(avg_rating, 2)}/5.0). This threatens overall business performance. Immediate executive attention required.',
                    priority='critical',
                    metric_impact=f'category_{cat["category"]}_critical'
                )
            elif count > (total_feedback * 0.25) and avg_rating < 3.5:
                self._add_recommendation(
                    type='warning',
                    title=f'{category_name} Improvement Priority',
                    description=f'{category_name} is a major feedback driver ({round(percentage)}% of volume) with suboptimal ratings ({round(avg_rating, 2)}/5.0). Improving this area by 1 point could increase overall rating by ~{round(0.25 * (3.5 - avg_rating), 2)} points.',
                    priority='high',
                    metric_impact=f'category_{cat["category"]}_high'
                )
            elif count > (total_feedback * 0.30) and avg_rating >= 4.3:
                self._add_recommendation(
                    type='success',
                    title=f'{category_name} Competitive Advantage',
                    description=f'{category_name} is a core strength: {round(percentage)}% of feedback with outstanding ratings ({round(avg_rating, 2)}/5.0). Document best practices here and apply to other areas. Consider making this a marketing message.',
                    priority='low',
                    metric_impact=f'category_{cat["category"]}_strength'
                )
            elif avg_rating >= 3.5 and avg_rating < 4.0 and count > (total_feedback * 0.15):
                self._add_recommendation(
                    type='info',
                    title=f'{category_name} Improvement Opportunity',
                    description=f'{category_name} shows moderate performance ({round(avg_rating, 2)}/5.0) with meaningful volume ({round(percentage)}%). This is a leverage point: targeted improvements here could significantly boost overall satisfaction.',
                    priority='medium',
                    metric_impact=f'category_{cat["category"]}_opportunity'
                )
    
    def _analyze_trend_momentum(self, trend_data: List[Dict]):
        """Analyze trends with velocity and acceleration metrics."""
        if len(trend_data) < 10:
            return
            
        # Split into recent vs previous periods
        recent_days = trend_data[-7:]
        previous_days = trend_data[-14:-7] if len(trend_data) >= 14 else trend_data[-10:-7]
        
        if len(recent_days) < 5 or len(previous_days) < 5:
            return
        
        # Volume trends
        recent_avg_volume = sum(d['count'] for d in recent_days) / len(recent_days)
        previous_avg_volume = sum(d['count'] for d in previous_days) / len(previous_days)
        
        if previous_avg_volume > 0:
            volume_growth = ((recent_avg_volume - previous_avg_volume) / previous_avg_volume) * 100
        else:
            volume_growth = 0
        
        # Rating trends
        recent_avg_rating = sum(d['avg_rating'] for d in recent_days) / len(recent_days)
        previous_avg_rating = sum(d['avg_rating'] for d in previous_days) / len(previous_days)
        rating_change = recent_avg_rating - previous_avg_rating
        
        # Interpret combined signals
        if volume_growth > 40:
            self._add_recommendation(
                type='success',
                title='Rapid Engagement Growth',
                description=f'Feedback volume surged {round(volume_growth)}% vs previous week. Your engagement strategies (QR code placement, staff prompts, incentives) are working exceptionally well.',
                priority='low',
                metric_impact='volume_surge'
            )
        elif volume_growth < -35:
            self._add_recommendation(
                type='warning',
                title='Declining Customer Engagement',
                description=f'Feedback volume dropped {abs(round(volume_growth))}% compared to previous week. Review QR code visibility, staff engagement, and customer touchpoints. Consider refreshing your feedback campaign.',
                priority='high',
                metric_impact='volume_decline'
            )
        
        if rating_change > 0.4:
            self._add_recommendation(
                type='success',
                title='Strong Satisfaction Improvement',
                description=f'Average ratings trending upward ({round(previous_avg_rating, 2)} → {round(recent_avg_rating, 2)}). Recent changes or improvements are positively impacting customer experience.',
                priority='low',
                metric_impact='rating_improvement'
            )
        elif rating_change < -0.4:
            self._add_recommendation(
                type='warning',
                title='Concerning Satisfaction Decline',
                description=f'Average ratings trending downward ({round(previous_avg_rating, 2)} → {round(recent_avg_rating, 2)}). Investigate recent operational changes, staffing issues, or service disruptions.',
                priority='high',
                metric_impact='rating_decline'
            )
    
    def _analyze_engagement_health(self, recent_feedback: int, total_feedback: int):
        """Assess current engagement levels relative to historical baseline."""
        expected_weekly = total_feedback / 4  # Assuming ~4 weeks of data
        ratio = recent_feedback / expected_weekly if expected_weekly > 0 else 0
        
        if ratio < 0.4:
            self._add_recommendation(
                type='warning',
                title='Critically Low Engagement',
                description=f'Recent feedback ({recent_feedback}) is less than 40% of expected weekly average ({round(expected_weekly)}). This suggests QR code visibility issues or staff not promoting feedback collection.',
                priority='high',
                metric_impact='engagement_critical'
            )
        elif ratio < 0.6:
            self._add_recommendation(
                type='warning',
                title='Below-Target Engagement',
                description=f'Recent feedback ({recent_feedback}) is below expected levels ({round(expected_weekly)} weekly average). Refresh staff reminders and verify QR codes are prominently displayed.',
                priority='medium',
                metric_impact='engagement_low'
            )
        elif ratio > 1.6:
            self._add_recommendation(
                type='success',
                title='Exceptional Engagement Momentum',
                description=f'Recent feedback ({recent_feedback}) exceeds expected average by {round((ratio - 1) * 100)}%. Strong execution on feedback collection. Capture this momentum!',
                priority='low',
                metric_impact='engagement_high'
            )
    
    def _analyze_service_quality_matrix(self, categories: List[Dict]):
        """Compare service categories against other areas."""
        service_keywords = ['service', 'staff', 'support', 'customer service']
        
        for cat in categories:
            if cat['category'].lower() in service_keywords:
                service_rating = cat['avg_rating']
                service_count = cat['count']
                
                # Calculate weighted average of non-service categories
                other_weighted_sum = 0
                other_count = 0
                for c in categories:
                    if c['category'].lower() not in service_keywords:
                        other_weighted_sum += c['avg_rating'] * c['count']
                        other_count += c['count']
                
                if other_count > 0:
                    other_avg = other_weighted_sum / other_count
                    gap = service_rating - other_avg
                    
                    if gap < -0.6 and service_count > 15:
                        self._add_recommendation(
                            type='warning',
                            title='Service Quality Gap Detected',
                            description=f'Service-related feedback ({round(service_rating, 2)}/5.0) rates {abs(round(gap, 2))} points lower than other areas ({round(other_avg, 2)}/5.0). This {service_count}-response pattern indicates systemic customer service issues requiring comprehensive training program.',
                            priority='high',
                            metric_impact='service_gap_critical'
                        )
                    elif gap < -0.3 and service_count > 10:
                        self._add_recommendation(
                            type='info',
                            title='Service Quality Opportunity',
                            description=f'Service ratings ({round(service_rating, 2)}/5.0) lag behind other areas ({round(other_avg, 2)}/5.0) by {abs(round(gap, 2))} points. Enhancing customer service skills could significantly improve overall satisfaction.',
                            priority='medium',
                            metric_impact='service_gap_moderate'
                        )
    
    def _analyze_statistical_confidence(self, total_feedback: int):
        """Assess data reliability and provide guidance."""
        if total_feedback < 15:
            self._add_recommendation(
                type='info',
                title='Limited Data - Early Stage',
                description=f'With {total_feedback} responses, patterns may not yet be statistically reliable. Continue collecting feedback; insights will become more actionable after 30+ responses.',
                priority='medium',
                metric_impact='data_limited'
            )
        elif total_feedback < 30:
            self._add_recommendation(
                type='info',
                title='Building Statistical Significance',
                description=f'You have {total_feedback} feedback responses—good progress! For highly reliable insights across multiple categories, aim for 50+ responses. Keep promoting your feedback QR code.',
                priority='low',
                metric_impact='data_building'
            )
    
    def _add_recommendation(self, type: str, title: str, description: str, 
                           priority: str, metric_impact: str):
        """Add a recommendation to the list."""
        self.recommendations.append({
            'type': type,
            'title': title,
            'description': description,
            'priority': priority,
            'metric_impact': metric_impact
        })


def main():
    """Main entry point for CLI usage."""
    if len(sys.argv) > 1:
        # Read JSON data from command line argument or file
        try:
            data = json.loads(sys.argv[1])
            engine = FeedbackAnalyticsEngine()
            recommendations = engine.analyze(data)
            print(json.dumps(recommendations, indent=2))
        except json.JSONDecodeError as e:
            print(f"Error parsing JSON: {e}", file=sys.stderr)
            sys.exit(1)
    else:
        print("Usage: python advanced_analytics.py '<json_data>'")
        sys.exit(1)


if __name__ == "__main__":
    main()
