--database PhpSpider_Magnet2BitTorrent

--table infohash_table

CREATE TABLE `infohash_table` (
  `info_id` INT(10) UNSIGNED NOT NULL,
  `infohash` CHAR(40) NOT NULL,
  `createtime`TIMESTAMP NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 


-- Indexes for table `infohash_table`

ALTER TABLE `infohash_table`
  ADD PRIMARY KEY (`info_id`),
  ADD KEY `infohash` (`infohash`) USING BTREE;

-- AUTO_INCREMENT for table `infohash_table`

ALTER TABLE `infohash_table`
  MODIFY `info_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

-- mysql自动创建createtime 设置默认时间 CURRENT_TIMESTAMP 

ALTER TABLE `infohash_table`
MODIFY COLUMN  `createtime` TIMESTAMP  DEFAULT CURRENT_TIMESTAMP ;